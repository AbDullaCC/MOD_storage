<?php

use Database\Seeders\DemoInventorySeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.mysql');
if (! app()->environment('local') || config('database.default') !== 'mysql' || $database['database'] !== 'mod_storage' || ! empty($database['url'])) {
    throw new RuntimeException('This reset only supports the local mod_storage MySQL database.');
}
$dumpExecutable = $argv[1] ?? '';
if (! is_file($dumpExecutable)) {
    throw new RuntimeException('Pass the installed mysqldump executable as the first argument.');
}
$backupDirectory = storage_path('app/backups');
if (! is_dir($backupDirectory)) {
    mkdir($backupDirectory, 0770, true);
}
$backup = $backupDirectory.'/before-demo-reset-'.now()->format('Ymd-His-u').'.sql';
$dump = new Process([
    $dumpExecutable, '--host='.$database['host'], '--port='.$database['port'], '--user='.$database['username'],
    '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF',
    '--default-character-set=utf8mb4', '--result-file='.$backup, $database['database'],
], base_path(), ['MYSQL_PWD' => $database['password']], null, 60);
$dump->mustRun();
if (! is_file($backup) || filesize($backup) === 0) {
    throw new RuntimeException('Backup failed; no inventory data was changed.');
}
$usersBefore = DB::table('users')->orderBy('id')->get()->toJson();
DB::transaction(function () use ($usersBefore) {
    // This explicitly requested local demo reset bypasses normal history deletion restrictions.
    // Child-first deletes keep foreign keys enabled and roll back together with demo creation.
    foreach (['inventory_audits', 'outs', 'additions', 'product_ins'] as $table) {
        DB::table($table)->delete();
    }
    app(DemoInventorySeeder::class)->run();
    if ($usersBefore !== DB::table('users')->orderBy('id')->get()->toJson()) {
        throw new RuntimeException('Login accounts changed; rolling back the reset.');
    }
});
echo 'Backup: '.$backup.PHP_EOL;
foreach (['product_ins', 'additions', 'outs', 'inventory_audits'] as $table) {
    echo $table.': '.DB::table($table)->count().PHP_EOL;
}
echo 'Login accounts preserved.'.PHP_EOL;
