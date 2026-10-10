<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.mysql');
if (! app()->environment('local') || config('database.default') !== 'mysql' || $database['database'] !== 'mod_storage' || ! empty($database['url'])) {
    throw new RuntimeException('This local upgrade requires the mod_storage MySQL database without a DATABASE_URL override.');
}
$columns = ['cancelled_at', 'cancelled_by', 'cancelled_by_name', 'cancellation_reason', 'active_serial_number'];
if (collect($columns)->every(fn ($column) => Schema::hasColumn('product_ins', $column))) {
    echo "Item cancellation schema is already installed.\n";
    exit;
}
$duplicates = DB::table('product_ins')->select('serial_number')->whereNotNull('serial_number');
if (Schema::hasColumn('product_ins', 'cancelled_at')) {
    $duplicates->whereNull('cancelled_at');
}
if ($duplicates->groupBy('serial_number')->havingRaw('COUNT(*) > 1')->exists()) {
    throw new RuntimeException('Duplicate serial numbers must be resolved before installing the active-serial constraint. No schema changes were made.');
}
$originalColumns = Schema::getColumnListing('product_ins');
$tables = ['product_ins', 'outs', 'additions', 'users', 'inventory_audits'];
$snapshot = static function () use ($tables, $originalColumns): array {
    $result = [];
    foreach ($tables as $table) {
        $rows = DB::table($table)->orderBy('id')->get($table === 'product_ins' ? $originalColumns : ['*']);
        $result[$table] = ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
    }

    return $result;
};
$before = $snapshot();
$stamp = now()->format('Ymd-His');
$backupDirectory = storage_path('app/backups');
if (! is_dir($backupDirectory)) {
    mkdir($backupDirectory, 0770, true);
}
$backup = $backupDirectory.'/before-employee-corrections-'.$stamp.'.sql';
$dumpExecutable = $argv[1] ?? '';
if (! is_file($dumpExecutable)) {
    throw new RuntimeException('Pass the installed mysqldump executable path as the first argument.');
}
$dump = new Process([
    $dumpExecutable, '--host='.$database['host'], '--port='.$database['port'], '--user='.$database['username'],
    '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF',
    '--default-character-set=utf8mb4', '--result-file='.$backup, $database['database'],
], base_path(), ['MYSQL_PWD' => $database['password']], null, 60);
$dump->mustRun();
if (! is_file($backup) || filesize($backup) === 0) {
    throw new RuntimeException('Database backup was not created. No schema changes were made.');
}

Schema::table('product_ins', function (Blueprint $table) {
    if (! Schema::hasColumn('product_ins', 'cancelled_at')) {
        $table->timestamp('cancelled_at')->nullable();
    }
    if (! Schema::hasColumn('product_ins', 'cancelled_by')) {
        $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
    }
    if (! Schema::hasColumn('product_ins', 'cancelled_by_name')) {
        $table->string('cancelled_by_name')->nullable();
    }
    if (! Schema::hasColumn('product_ins', 'cancellation_reason')) {
        $table->text('cancellation_reason')->nullable();
    }
    if (! Schema::hasColumn('product_ins', 'active_serial_number')) {
        $table->string('active_serial_number')->nullable()->storedAs('CASE WHEN cancelled_at IS NULL THEN serial_number ELSE NULL END');
        $table->unique('active_serial_number');
    }
});
$after = $snapshot();
$report = $backupDirectory.'/employee-corrections-verification-'.$stamp.'.json';
file_put_contents($report, json_encode(['backup' => $backup, 'before' => $before, 'after' => $after, 'preserved' => $before === $after], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
if ($before !== $after) {
    throw new RuntimeException('Existing-data verification failed. Inspect the backup and verification report.');
}
echo 'Backup: '.$backup.PHP_EOL;
echo 'Verification: '.$report.PHP_EOL;
echo "Schema upgraded; all original fields and records are unchanged.\n";
