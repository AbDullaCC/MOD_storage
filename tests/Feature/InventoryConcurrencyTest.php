<?php

namespace Tests\Feature;

use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    // Fixtures must be committed so independent worker connections can see them.
    use DatabaseMigrations;

    public static function withdrawals(): array
    {
        return [
            'combined withdrawals exceed stock' => [7, 7, 'rejected', 3],
            'combined withdrawals exactly consume stock' => [4, 6, 'accepted', 0],
        ];
    }

    #[DataProvider('withdrawals')]
    public function test_competing_withdrawals_use_current_stock_and_save_matching_audits(int $firstQuantity, int $secondQuantity, string $secondStatus, int $remaining): void
    {
        $item = ProductIn::create(['name' => 'Concurrent item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()]);
        $firstActor = User::factory()->create(['name' => 'First operator']);
        $secondActor = User::factory()->create(['name' => 'Second operator']);
        [$first, $firstInput] = $this->worker($item, $firstActor, $firstQuantity, true);
        [$second, $secondInput] = $this->worker($item, $secondActor, $secondQuantity, false);

        [$firstResult, $secondResult] = $this->compete($first, $firstInput, $second, $secondInput);
        $this->assertSame('accepted', $firstResult['status']);
        $this->assertSame($secondStatus, $secondResult['status']);
        if ($secondStatus === 'rejected') {
            $this->assertArrayHasKey('inventory', $secondResult['errors']);
        }

        $accepted = $secondStatus === 'accepted' ? 2 : 1;
        $this->assertSame($remaining, $item->fresh()->current_stock);
        $this->assertDatabaseCount('outs', $accepted);
        $this->assertDatabaseCount('inventory_audits', $accepted);
        $this->assertSame(10 - $remaining, (int) Out::sum('quantity'));
        $expected = [[$firstActor, $firstQuantity, 10, 10 - $firstQuantity, $firstResult['record_id']]];
        if ($secondStatus === 'accepted') {
            $expected[] = [$secondActor, $secondQuantity, 10 - $firstQuantity, $remaining, $secondResult['record_id']];
        } else {
            $this->assertDatabaseMissing('outs', ['created_by' => $secondActor->id]);
            $this->assertDatabaseMissing('inventory_audits', ['actor_id' => $secondActor->id]);
        }
        foreach (InventoryAudit::orderBy('id')->get() as $index => $audit) {
            [$actor, $quantity, $before, $after, $recordId] = $expected[$index];
            $this->assertSame('out', $audit->record_type);
            $this->assertSame('created', $audit->action);
            $this->assertSame($item->id, $audit->product_in_id);
            $this->assertSame($recordId, $audit->record_id);
            $this->assertSame($actor->id, $audit->actor_id);
            $this->assertSame($actor->name, $audit->actor_name);
            $this->assertNull($audit->before_values);
            $this->assertSame($quantity, $audit->after_values['quantity']);
            $this->assertSame($actor->id, $audit->after_values['created_by']);
            $this->assertSame($actor->name, $audit->after_values['created_by_name']);
            $this->assertSame($before, $audit->stock_before);
            $this->assertSame($after, $audit->stock_after);
            $this->assertDatabaseHas('outs', ['id' => $recordId, 'created_by' => $actor->id, 'quantity' => $quantity]);
        }
    }

    public static function itemReplacementRace(): array
    {
        return [
            'movement commits first' => ['withdraw', 'replace_item'],
            'replacement commits first' => ['replace_item', 'withdraw'],
        ];
    }

    #[DataProvider('itemReplacementRace')]
    public function test_first_movement_and_unused_item_replacement_cannot_both_succeed(string $firstOperation, string $secondOperation): void
    {
        $operator = User::factory()->create();
        $item = ProductIn::createRecorded(['name' => 'Concurrent item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()], $operator);
        [$first, $firstInput] = $this->worker($item, $operator, $firstOperation === 'withdraw' ? 4 : 8, true, ['operation' => $firstOperation]);
        [$second, $secondInput] = $this->worker($item, $operator, $secondOperation === 'withdraw' ? 4 : 8, false, ['operation' => $secondOperation]);
        [$firstResult, $secondResult] = $this->compete($first, $firstInput, $second, $secondInput);
        $this->assertSame('accepted', $firstResult['status']);
        $this->assertSame('rejected', $secondResult['status']);
        $this->assertArrayHasKey('inventory', $secondResult['errors']);
        if ($firstOperation === 'withdraw') {
            $this->assertNull($item->fresh()->cancelled_at);
            $this->assertSame(6, $item->fresh()->current_stock);
            $this->assertDatabaseCount('product_ins', 1);
            $this->assertDatabaseCount('outs', 1);
            $this->assertDatabaseCount('inventory_audits', 1);
        } else {
            $this->assertNotNull($item->fresh()->cancelled_at);
            $this->assertSame(0, $item->fresh()->current_stock);
            $this->assertSame(8, ProductIn::findOrFail($firstResult['record_id'])->current_stock);
            $this->assertDatabaseCount('product_ins', 2);
            $this->assertDatabaseCount('outs', 0);
            $this->assertDatabaseCount('inventory_audits', 2);
        }
    }

    public function test_movement_cancellation_and_competing_withdrawal_preserve_stock_and_audit_sequence(): void
    {
        $operator = User::factory()->create();
        $other = User::factory()->create();
        $item = ProductIn::createRecorded(['name' => 'Concurrent item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()], $operator);
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 7, 'date' => now()], $operator);
        [$first, $firstInput] = $this->worker($item, $operator, 5, true, ['operation' => 'cancel_out', 'movement_id' => $out->id]);
        [$second, $secondInput] = $this->worker($item, $other, 4, false);
        [$firstResult, $secondResult] = $this->compete($first, $firstInput, $second, $secondInput);
        $this->assertSame('accepted', $firstResult['status']);
        $this->assertSame('accepted', $secondResult['status']);
        $this->assertSame(6, $item->fresh()->current_stock);
        $this->assertNotNull($out->fresh()->cancelled_at);
        $this->assertDatabaseCount('outs', 2);
        $this->assertDatabaseCount('inventory_audits', 3);
        $audits = InventoryAudit::orderBy('id')->get();
        $this->assertSame([10, 3, 10], $audits->pluck('stock_before')->all());
        $this->assertSame([3, 10, 6], $audits->pluck('stock_after')->all());
        $this->assertSame($other->id, $audits[2]->actor_id);
    }

    private function compete(Process $first, InputStream $firstInput, Process $second, InputStream $secondInput): array
    {
        try {
            $first->start();
            $second->start();
            $firstReady = $this->awaitPhase($first, 'ready');
            $secondReady = $this->awaitPhase($second, 'ready');
            $this->assertNotSame($firstReady['connection_id'], $secondReady['connection_id']);
            $firstInput->write("start\n");
            $this->awaitPhase($first, 'locked');
            $secondInput->write("start\n");
            $this->awaitPhase($second, 'attempting_lock');
            $deadline = microtime(true) + 0.3;
            do {
                $this->assertTrue($first->isRunning(), $first->getErrorOutput());
                $this->assertTrue($second->isRunning(), $second->getErrorOutput());
                $this->assertNull($this->phase($second, 'locked'));
                $this->assertNull($this->phase($second, 'result'));
                usleep(10000);
            } while (microtime(true) < $deadline);
            $firstInput->write("release\n");
            $firstInput->close();
            $firstResult = $this->awaitPhase($first, 'result');
            $this->awaitPhase($second, 'locked');
            $secondResult = $this->awaitPhase($second, 'result');
            $secondInput->close();
            $this->assertSame(0, $first->wait(), $first->getErrorOutput());
            $this->assertSame(0, $second->wait(), $second->getErrorOutput());

            return [$firstResult, $secondResult];
        } finally {
            foreach ([[$first, $firstInput], [$second, $secondInput]] as [$process, $input]) {
                if (! $input->isClosed()) {
                    $input->write("release\n");
                    $input->close();
                }
                if ($process->isStarted()) {
                    $process->stop(0);
                }
            }
        }
    }

    private function worker(ProductIn $item, User $actor, int $quantity, bool $holdLock, array $operation = []): array
    {
        $database = config('database.connections.mysql');
        $input = new InputStream;
        $input->write(json_encode([
            'product_id' => $item->id, 'actor_id' => $actor->id, 'quantity' => $quantity,
            'destination' => $actor->name.' destination', 'hold_lock' => $holdLock, ...$operation,
        ], JSON_THROW_ON_ERROR)."\n");
        $process = new Process([PHP_BINARY, base_path('tests/Support/withdrawal-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'mod_storage_testing',
            'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'],
            'DB_SOCKET' => $database['unix_socket'], 'CACHE_DRIVER' => 'array', 'LOG_CHANNEL' => 'stderr',
        ], $input, 15);

        return [$process, $input];
    }

    private function awaitPhase(Process $process, string $phase): array
    {
        $deadline = microtime(true) + 10;
        do {
            $process->checkTimeout();
            if ($message = $this->phase($process, $phase)) {
                return $message;
            }
            $this->assertTrue($process->isRunning(), 'Worker exited before '.$phase.': '.$process->getErrorOutput());
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Worker did not reach '.$phase.': '.$process->getErrorOutput());
    }

    private function phase(Process $process, string $phase): ?array
    {
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            $message = json_decode($line, true);
            if (($message['phase'] ?? null) === $phase) {
                return $message;
            }
        }

        return null;
    }
}
