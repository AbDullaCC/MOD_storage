<?php

namespace Tests\Feature;

use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
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

            // The second service must not acquire the item lock or finish while
            // the first holds it. Poll both processes so their stdin stays flowing.
            $deadline = microtime(true) + 0.3;
            do {
                $this->assertTrue($first->isRunning(), $first->getErrorOutput());
                $this->assertTrue($second->isRunning(), $second->getErrorOutput());
                $this->assertNull($this->phase($second, 'locked'), 'Competing withdrawal bypassed the item lock.');
                $this->assertNull($this->phase($second, 'result'), 'Competing withdrawal finished while the item was locked.');
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
        } finally {
            // Release/terminate workers before DatabaseMigrations removes tables,
            // even if an assertion fails while a worker holds a transaction.
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

    private function worker(ProductIn $item, User $actor, int $quantity, bool $holdLock): array
    {
        $database = config('database.connections.mysql');
        $input = new InputStream;
        $input->write(json_encode([
            'product_id' => $item->id, 'actor_id' => $actor->id, 'quantity' => $quantity,
            'destination' => $actor->name.' destination', 'hold_lock' => $holdLock,
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
