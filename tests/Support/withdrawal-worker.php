<?php

use App\Models\Out;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\CreatesApplication;

require __DIR__.'/../../vendor/autoload.php';

$emit = static function (array $message): void {
    echo json_encode($message, JSON_THROW_ON_ERROR).PHP_EOL;
    flush();
};
$command = static function (string $expected): void {
    if (trim((string) fgets(STDIN)) !== $expected) {
        throw new RuntimeException('Missing worker command: '.$expected);
    }
};

try {
    $input = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
    // Use the same test-database guard as PHPUnit before opening a connection.
    (new class
    {
        use CreatesApplication;
    })->createApplication();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
    $actor = User::findOrFail($input['actor_id']);
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    $isItemLock = static fn (string $sql): bool => str_contains(strtolower($sql), '`product_ins`') && str_contains(strtolower($sql), 'for update');

    DB::connection()->beforeExecuting(static function (string $sql) use ($isItemLock, $emit): void {
        if ($isItemLock($sql)) {
            $emit(['phase' => 'attempting_lock']);
        }
    });
    $held = false;
    DB::listen(static function (QueryExecuted $query) use ($isItemLock, $emit, $command, $input, &$held): void {
        if (! $held && $isItemLock($query->sql)) {
            $held = true;
            $emit(['phase' => 'locked']);
            // Hold the real service transaction open until the other worker competes.
            if ($input['hold_lock']) {
                $command('release');
            }
        }
    });

    $emit(['phase' => 'ready', 'connection_id' => $connectionId]);
    $command('start');
    try {
        $data = [
            'product_in_id' => $input['product_id'], 'quantity' => $input['quantity'],
            'date' => now(), 'destination' => $input['destination'],
        ];
        $service = app(InventoryService::class);
        $record = match ($input['operation'] ?? 'withdraw') {
            'replace_item' => $service->updateItem($input['product_id'], [
                'name' => 'Corrected concurrent item', 'category' => 'Test', 'quantity' => $input['quantity'], 'added_at' => now(),
            ], 'Wrong initial quantity', $actor),
            'replace_out' => $service->updateMovement(Out::class, $input['movement_id'], $data, 'Wrong withdrawn quantity', $actor),
            'cancel_out' => (function () use ($service, $input, $actor) {
                $service->cancel(Out::class, $input['movement_id'], 'Incorrect withdrawal', $actor);

                return Out::findOrFail($input['movement_id']);
            })(),
            default => $service->createMovement(Out::class, $data, $actor),
        };
        $emit(['phase' => 'result', 'status' => 'accepted', 'record_id' => $record->id]);
    } catch (ValidationException $exception) {
        $emit(['phase' => 'result', 'status' => 'rejected', 'errors' => $exception->errors()]);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
