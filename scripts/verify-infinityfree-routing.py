"""Exercise the real deployment rewrite rules on an isolated loopback Apache fixture."""
import argparse
import json
from pathlib import Path
import shutil
import ssl
import subprocess
import time
import urllib.error
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--apache-root', type=Path, required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
qa = root / 'dist' / 'routing-verification'
web = qa / 'htdocs'
(web / 'public/css').mkdir(parents=True, exist_ok=True)
shutil.copy2(root / 'deployment/infinityfree.htaccess', web / '.htaccess')
shutil.copy2(root / 'public/.htaccess', web / 'public/.htaccess')
(web / 'public/index.php').write_text('FRONT_CONTROLLER', encoding='utf-8')
(web / 'public/css/test.css').write_text('PUBLIC_ASSET', encoding='utf-8')
for name in ['.env', 'composer.json', 'vendor/autoload.php', 'storage/logs/laravel.log', 'storage/framework/sessions/example', 'storage/app/backups/example.sql', 'database/database.sqlite']:
    target = web / name
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text('FAKE_PRIVATE_MARKER', encoding='utf-8')
subprocess.run([shutil.which('openssl'), 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', str(qa / 'key.pem'), '-out', str(qa / 'cert.pem'), '-days', '1', '-subj', '/CN=localhost', '-addext', 'subjectAltName=IP:127.0.0.1,DNS:localhost'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
apache = args.apache_root.resolve()
conf = f'ServerRoot "{apache.as_posix()}"\nServerName localhost\nListen 127.0.0.1:18080\nListen 127.0.0.1:18081\n'
conf += ''.join(f'LoadModule {name}_module modules/mod_{name}.so\n' for name in ['authz_core', 'dir', 'mime', 'log_config', 'rewrite', 'socache_shmcb', 'ssl'])
conf += f'''PidFile "{(qa / 'apache.pid').as_posix()}"
ErrorLog "{(qa / 'apache.log').as_posix()}"
TypesConfig "{(apache / 'conf/mime.types').as_posix()}"
DocumentRoot "{web.as_posix()}"
DirectoryIndex index.php
<Directory "{web.as_posix()}">
Options FollowSymLinks
AllowOverride All
Require all granted
</Directory>
<VirtualHost 127.0.0.1:18081>
SSLEngine on
SSLCertificateFile "{(qa / 'cert.pem').as_posix()}"
SSLCertificateKeyFile "{(qa / 'key.pem').as_posix()}"
</VirtualHost>
'''
(qa / 'apache.conf').write_text(conf, encoding='utf-8')
command = [str(apache / 'bin/httpd.exe'), '-f', str(qa / 'apache.conf')]
subprocess.run([*command, '-t'], check=True)
process = subprocess.Popen([*command, '-DFOREGROUND'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW)
try:
    # Trust only this fixture certificate; normal TLS validation remains enabled.
    context = ssl.create_default_context(cafile=str(qa / 'cert.pem'))
    deadline = time.monotonic() + 10
    while True:
        try:
            urllib.request.urlopen('https://127.0.0.1:18081/login', context=context, timeout=2).close()
            break
        except urllib.error.URLError:
            if time.monotonic() >= deadline or process.poll() is not None:
                raise
            time.sleep(.2)
    results = {}
    # Include the actual post-login URL, all inventory forms, and private paths.
    routes = ['/', '/login', '/storage', '/storage/', '/storage/create', '/storage/export', '/storage/report', '/storage/item/1/history', '/audits', '/users', '/public/storage']
    routes += [('/storage', 'POST'), ('/storage/addition', 'POST'), ('/storage/out', 'POST'), ('/storage/item/1', 'PUT'), ('/storage/out/1', 'PUT'), ('/storage/addition/1', 'PUT'), ('/storage/item/1/cancel', 'DELETE'), ('/storage/out/1', 'DELETE'), ('/storage/addition/1', 'DELETE'), ('/storage/item/1/restore', 'POST')]
    paths = routes + ['/css/test.css', '/.env', '/composer.json', '/vendor/autoload.php', '/storage/logs/laravel.log', '/storage/framework/sessions/example', '/storage/app/backups/example.sql', '/database/database.sqlite', '/public/../.env', '/.git/config']
    for entry in paths:
        path, method = entry if isinstance(entry, tuple) else (entry, 'GET')
        request = urllib.request.Request('https://127.0.0.1:18081' + path, method=method)
        try:
            with urllib.request.urlopen(request, context=context, timeout=3) as response:
                status, body = response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            status, body = error.code, error.read().decode()
        results[method + ' ' + path] = status
        if entry in routes:
            # Apache's static fixture rejects PUT/DELETE after routing (405).
            # Laravel's real handlers are covered separately by the MySQL tests.
            if method in ('PUT', 'DELETE'):
                assert status == 405, (method, path, status)
            else:
                assert status == 200 and body == 'FRONT_CONTROLLER', (method, path, status)
        elif path == '/css/test.css':
            assert status == 200 and body == 'PUBLIC_ASSET'
        else:
            assert status in (403, 404) and 'FAKE_PRIVATE_MARKER' not in body, (path, status)
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None
    try:
        urllib.request.build_opener(NoRedirect).open('http://127.0.0.1:18080/storage', timeout=3)
        raise AssertionError('HTTP must redirect to HTTPS')
    except urllib.error.HTTPError as error:
        assert error.code == 301 and error.headers['Location'] == 'https://127.0.0.1:18080/storage'
        results['http_redirect'] = 301
    (qa / 'results.json').write_text(json.dumps(results, indent=2), encoding='utf-8')
    print(f'Passed {len(results)} routing checks: inventory URLs allowed, private files blocked, HTTPS enforced.')
finally:
    if process.poll() is None:
        # Stop this newly spawned process tree only, never the Laragon service.
        subprocess.run(['taskkill', '/PID', str(process.pid), '/T', '/F'], check=True, stdout=subprocess.DEVNULL)
        process.wait(timeout=10)
