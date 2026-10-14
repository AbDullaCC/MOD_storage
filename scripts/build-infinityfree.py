"""Build an isolated upload folder; never copy the local .env, database, or storage."""
import base64
import datetime
import hashlib
import json
from pathlib import Path
import secrets
import shutil
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
release = root / 'dist' / ('infinityfree-' + datetime.datetime.now().strftime('%Y%m%d-%H%M%S') + '-' + secrets.token_hex(3))
htdocs = release / 'htdocs'
htdocs.mkdir(parents=True)

def run(args, cwd=root):
    executable = shutil.which(args[0])
    if not executable:
        raise RuntimeError(f'{args[0]} is not installed or on PATH')
    command = [executable, *args[1:]]
    if Path(executable).suffix.lower() in {'.bat', '.cmd'}:
        command = ['cmd.exe', '/d', '/c', subprocess.list2cmdline(command)]
    subprocess.run(command, cwd=cwd, check=True)

run(['npm', 'run', 'build'])
for directory in ['app', 'config', 'routes', 'resources/views', 'database/migrations']:
    shutil.copytree(root / directory, htdocs / directory)
for file in ['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php']:
    target = htdocs / file
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(root / file, target)
shutil.copytree(root / 'public', htdocs / 'public', ignore=shutil.ignore_patterns('hot', 'storage'))
for directory in ['bootstrap/cache', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs']:
    target = htdocs / directory
    target.mkdir(parents=True, exist_ok=True)
    (target / '.gitignore').write_text('*\n!.gitignore\n', encoding='utf-8')
template = (root / 'deployment/infinityfree.env.example').read_text(encoding='utf-8')
key = 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode()
(htdocs / '.env').write_text(template.replace('GENERATED_BY_PACKAGE_SCRIPT', key), encoding='utf-8')
shutil.copy2(root / 'deployment/infinityfree.htaccess', htdocs / '.htaccess')

# Install into the isolated release so local PHPUnit/debugging tools stay available.
run(['composer', 'install', '--no-dev', '--no-scripts', '--prefer-dist', '--optimize-autoloader', '--no-interaction'], cwd=htdocs)
run(['php', 'artisan', 'package:discover', '--ansi'], cwd=htdocs)
run(['php', 'scripts/export-staging-database.php', str(release)])
shutil.copy2(root / 'docs/deployment.md', release / 'DEPLOYMENT.md')

files = sorted(path for path in htdocs.rglob('*') if path.is_file())
oversized = [str(path.relative_to(htdocs)) for path in files if path.suffix == '.php' and path.stat().st_size >= 1_000_000]
if oversized:
    raise RuntimeError('InfinityFree PHP upload limit exceeded: ' + ', '.join(oversized))
if any(path.is_symlink() for path in htdocs.rglob('*')):
    raise RuntimeError('Package must not contain symlinks')
report = {
    'file_count': len(files),
    'total_bytes': sum(path.stat().st_size for path in files),
    'largest_php_bytes': max(path.stat().st_size for path in files if path.suffix == '.php'),
    'no_dev_dependencies': not (htdocs / 'vendor/phpunit').exists(),
    'local_env_excluded': (htdocs / '.env').read_bytes() != (root / '.env').read_bytes(),
    'files': {str(path.relative_to(htdocs)).replace('\\', '/'): hashlib.sha256(path.read_bytes()).hexdigest() for path in files},
}
(release / 'package-report.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
with zipfile.ZipFile(release / 'htdocs.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(path, str(path.relative_to(htdocs)))
print('Release folder:', release)
print('Upload files:', len(files), '| Largest PHP file:', report['largest_php_bytes'], 'bytes')
print('Edit htdocs/.env placeholders before uploading. SQL and passwords stay outside htdocs.')
