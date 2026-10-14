<?php

use App\Models\InventoryAudit;
use App\Models\ProductIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

require __DIR__.'/../vendor/autoload.php';
$app = (new class
{
    use CreatesApplication;
})->createApplication();
if (! app()->environment('testing')) {
    throw new RuntimeException('Import verification requires APP_ENV=testing and the dedicated test database.');
}
$directory = realpath($argv[1] ?? '');
$dist = realpath(base_path('dist'));
if (! $directory || ! $dist || ! str_starts_with(strtolower($directory), strtolower($dist).DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Select a release inside this project\'s dist directory.');
}
$sql = file_get_contents($directory.'/staging-database.sql');
if (preg_match('/\b(?:USE|DROP\s+DATABASE|CREATE\s+DATABASE)\s/i', $sql)) {
    throw new RuntimeException('Staging SQL must not select or modify another database.');
}
Schema::dropAllTables();
DB::connection()->getPdo()->exec($sql);
$counts = json_decode(file_get_contents($directory.'/database-counts.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($counts as $table => $count) {
    if (DB::table($table)->count() !== $count) {
        throw new RuntimeException('Imported count mismatch: '.$table);
    }
}
foreach (file($directory.'/staging-accounts.txt', FILE_IGNORE_NEW_LINES) as $line) {
    if (! preg_match('/^(.+) \((admin|operator)\): ([a-f0-9]{32})$/', $line, $matches)) {
        continue;
    }
    $user = User::where('username', $matches[1])->firstOrFail();
    if (! Hash::check($matches[3], $user->password) || Hash::check('password', $user->password) || $user->remember_token !== null) {
        throw new RuntimeException('Imported staging credentials failed verification.');
    }
}
if (InventoryAudit::where('stock_before', '<', 0)->orWhere('stock_after', '<', 0)->exists()) {
    throw new RuntimeException('Negative saved audit stock.');
}
$balances = ProductIn::orderBy('id')->get()->map(fn ($item) => [
    'name' => $item->name, 'stock' => $item->current_stock,
    'cancelled' => (bool) $item->cancelled_at, 'archived' => (bool) $item->archived_at,
]);
if ($balances->contains(fn ($item) => $item['stock'] < 0)) {
    throw new RuntimeException('Negative current stock after import.');
}
foreach (['personal_access_tokens', 'password_reset_tokens', 'failed_jobs'] as $table) {
    if (DB::table($table)->exists()) {
        throw new RuntimeException('Private token/job data should not be exported.');
    }
}
file_put_contents($directory.'/import-verification.json', json_encode([
    'database' => 'mod_storage_testing', 'counts_match' => true, 'fresh_credentials_verified' => true,
    'tokens_excluded' => true, 'balances' => $balances,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo 'SQL imported successfully into mod_storage_testing; counts, passwords, and stock verified.'.PHP_EOL;
