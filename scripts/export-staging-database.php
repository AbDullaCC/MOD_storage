<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
if (! app()->environment('local') || config('database.default') !== 'mysql' || $connection['database'] !== 'mod_storage' || ! empty($connection['url'])) {
    throw new RuntimeException('Export only supports the local mod_storage database.');
}
$directory = realpath($argv[1] ?? '');
$dist = realpath(base_path('dist'));
if (! $directory || ! $dist || ! str_starts_with(strtolower($directory), strtolower($dist).DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Export directory must exist inside this project\'s dist folder.');
}
$tables = ['migrations', 'users', 'product_ins', 'additions', 'outs', 'inventory_audits', 'password_reset_tokens', 'personal_access_tokens', 'failed_jobs'];
$dataTables = array_slice($tables, 0, 6);
$sql = "-- Initial import into an EMPTY staging database only. No local credentials or tokens.\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$credentials = "Staging accounts only. Do not upload this file to your website.\nLocal passwords are unchanged.\n\n";
$counts = [];
DB::transaction(function () use ($tables, $dataTables, &$sql, &$credentials, &$counts) {
    $pdo = DB::connection()->getPdo();
    foreach ($tables as $table) {
        $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
        $sql .= $create['Create Table'].";\n";
        if (! in_array($table, $dataTables, true)) {
            continue;
        }
        $columns = collect(DB::select('SHOW FULL COLUMNS FROM `'.$table.'`'))
            ->reject(fn ($column) => str_contains($column->Extra, 'STORED GENERATED') || str_contains($column->Extra, 'VIRTUAL GENERATED'))->pluck('Field')->all();
        $rows = DB::table($table)->orderBy('id')->get($columns);
        $counts[$table] = $rows->count();
        foreach ($rows as $row) {
            $values = (array) $row;
            if ($table === 'users') {
                $password = bin2hex(random_bytes(16));
                $values['password'] = Hash::make($password);
                $values['remember_token'] = null;
                $credentials .= $row->username.' ('.$row->role.'): '.$password."\n";
            }
            $quoted = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $values);
            $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`,`', $columns).'`) VALUES ('.implode(',', $quoted).");\n";
        }
    }
});
file_put_contents($directory.'/staging-database.sql', $sql);
file_put_contents($directory.'/staging-accounts.txt', $credentials);
file_put_contents($directory.'/database-counts.json', json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Staging SQL exported with fresh account passwords; local database unchanged.'.PHP_EOL;
