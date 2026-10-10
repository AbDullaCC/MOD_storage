<?php

namespace Tests\Feature;

use App\Models\Addition;
use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function item(int $quantity = 10): ProductIn
    {
        return ProductIn::create(['name' => 'History item', 'category' => 'Test', 'quantity' => $quantity, 'added_at' => now()->subDay()]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_creations_have_complete_snapshots_and_stock_balances(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $this->post('/storage', ['name' => 'New item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()->subDay()->toDateTimeString()])->assertSessionHasNoErrors();
        $item = ProductIn::firstOrFail();
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()->subDay()->toDateTimeString(), 'source' => 'Supplier'])->assertSessionHasNoErrors();
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now()->subDay()->toDateTimeString(), 'destination' => 'Office'])->assertSessionHasNoErrors();
        $events = InventoryAudit::orderBy('id')->get();
        $this->assertCount(3, $events);
        foreach ($events as $index => $event) {
            $this->assertSame($actor->id, $event->actor_id);
            $this->assertSame($actor->name, $event->actor_name);
            $this->assertSame('created', $event->action);
            $this->assertNull($event->before_values);
            $this->assertSame($actor->id, $event->after_values['created_by']);
            $this->assertSame([0, 10, 15][$index], $event->stock_before);
            $this->assertSame([10, 15, 12][$index], $event->stock_after);
        }
    }

    public function test_multiple_edits_preserve_original_and_intermediate_values_and_ignore_forged_metadata(): void
    {
        $item = $this->item();
        $admin = $this->admin();
        foreach (['First correction', 'Second correction'] as $name) {
            $this->put('/storage/item/'.$item->id, [
                'name' => $name, 'category' => 'Test', 'added_at' => $item->added_at->toDateTimeString(),
                'reason' => 'Correct label', 'quantity' => 10, 'created_by' => $admin->id, 'archived_at' => now()->toDateTimeString(),
            ])->assertSessionHasNoErrors();
        }
        $events = InventoryAudit::orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame('History item', $events[0]->before_values['name']);
        $this->assertSame('First correction', $events[0]->after_values['name']);
        $this->assertSame('First correction', $events[1]->before_values['name']);
        $this->assertSame('Second correction', $events[1]->after_values['name']);
        $this->assertSame('Correct label', $events[1]->reason);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertNull($item->fresh()->created_by);
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame($admin->id, $events[1]->actor_id);
        $this->get('/audits?product_in_id='.$item->id)->assertOk()->assertSee('Correct label')->assertSee('History item')->assertSee('Second correction');
    }

    public function test_history_shows_real_stock_before_new_operations_and_original_fields_before_corrections(): void
    {
        $actor = $this->admin();
        $service = app(InventoryService::class);
        $item = $service->createItem(['name' => 'Audit display item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()], $actor);
        $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()], $actor);
        $out = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now(), 'destination' => 'Original office'], $actor);
        $service->edit(Out::class, $out->id, ['destination' => 'Corrected office', 'note' => 'Delivery corrected'], 'Correct destination', $actor);
        $service->cancel(Out::class, $out->id, 'Duplicate withdrawal', $actor);

        $response = $this->get('/audits?product_in_id='.$item->id)->assertOk();
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        $events = InventoryAudit::orderBy('id')->get();
        foreach ($events as $index => $event) {
            $article = '//tbody[@data-audit-event="'.$event->id.'"]';
            $balance = $article.'//table[@aria-label="رصيد المخزون قبل العملية وبعدها"]/tbody/tr';
            if ($event->stock_before !== $event->stock_after) {
                $this->assertSame((string) [0, 10, 15, 12, 12][$index], $xpath->evaluate('string('.$balance.'/*[2])'));
                $this->assertSame((string) [10, 15, 12, 12, 15][$index], $xpath->evaluate('string('.$balance.'/*[3])'));
            } else {
                $this->assertSame(0, $xpath->query($balance)->length);
            }
            $comparison = $article.'//table[@aria-label="مقارنة بيانات العملية"]';
            if ($event->action === 'created') {
                $this->assertSame(0, $xpath->query($comparison)->length);
                $this->assertSame(1, $xpath->query($article.'//dl')->length);
            } elseif ($event->action === 'edited') {
                $this->assertSame(0, $xpath->query($comparison)->length);
                $this->assertStringContainsString('Original office', $xpath->evaluate('string('.$article.'/tr[1])'));
                $this->assertSame('Corrected office', $xpath->evaluate('string('.$article.'//dl/div[dt="الوجهة"]/dd)'));
                $this->assertSame('Delivery corrected', $xpath->evaluate('string('.$article.'//dl/div[dt="ملاحظات"]/dd)'));
            } else {
                $this->assertSame(0, $xpath->query($comparison)->length);
                $this->assertSame('3', $xpath->evaluate('string('.$article.'//dl/div[dt="الكمية"]/dd)'));
                $this->assertSame('Corrected office', $xpath->evaluate('string('.$article.'//dl/div[dt="الوجهة"]/dd)'));
            }
        }
    }

    public function test_all_corrections_require_a_reason_without_saving_partial_changes(): void
    {
        $item = $this->item();
        $addition = $item->additions()->create(['quantity' => 2, 'date' => now()->subDay()]);
        $out = $item->outs()->create(['quantity' => 1, 'date' => now()->subDay()]);
        $this->admin();
        foreach ([['item', $item], ['addition', $addition], ['out', $out]] as [$type, $record]) {
            $this->put('/storage/'.$type.'/'.$record->id, ['name' => 'Invalid change', 'category' => 'Test', 'added_at' => now()->subDay()->toDateTimeString(), 'date' => now()->subDay()->toDateTimeString(), 'note' => 'Invalid change'])->assertSessionHasErrors('reason');
            $this->delete('/storage/'.$type.'/'.$record->id)->assertSessionHasErrors('reason');
        }
        $this->assertSame('History item', $item->fresh()->name);
        $this->assertNull($out->fresh()->cancelled_at);
        $this->assertNull($addition->fresh()->cancelled_at);
        $this->assertDatabaseCount('inventory_audits', 0);
    }

    public function test_withdrawal_cancellation_restores_stock_once_and_keeps_the_original_record(): void
    {
        $item = $this->item();
        $out = $item->outs()->create(['quantity' => 4, 'date' => now()->subDay(), 'destination' => 'Office']);
        $admin = $this->admin();
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Duplicate withdrawal', 'cancelled_by' => 999])->assertSessionHasNoErrors();
        $out->refresh();
        $this->assertSame(10, $item->current_stock);
        $this->assertSame(4, $out->quantity);
        $this->assertSame('Office', $out->destination);
        $this->assertSame($admin->id, $out->cancelled_by);
        $event = InventoryAudit::firstOrFail();
        $this->assertSame(6, $event->stock_before);
        $this->assertSame(10, $event->stock_after);
        $this->assertNull($event->before_values['cancelled_at']);
        $this->assertNotNull($event->after_values['cancelled_at']);
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Repeated cancellation'])->assertSessionHasErrors('inventory');
        $this->put('/storage/out/'.$out->id, ['date' => now()->subDay()->toDateTimeString(), 'note' => 'Change cancelled', 'reason' => 'Try editing cancelled'])->assertSessionHasErrors('inventory');
        $this->assertSame(10, $item->current_stock);
        $this->assertDatabaseCount('outs', 1);
        $this->assertDatabaseCount('inventory_audits', 1);
        $this->get('/audits')->assertOk()->assertSee('ملغاة')->assertSee('Duplicate withdrawal');
    }

    public function test_restock_cancellation_rejects_negative_stock_and_can_succeed_after_a_withdrawal_is_cancelled(): void
    {
        $item = $this->item(2);
        $addition = $item->additions()->create(['quantity' => 5, 'date' => now()->subDay()]);
        $out = $item->outs()->create(['quantity' => 6, 'date' => now()->subDay()]);
        $this->admin();
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Cancel restock'])->assertSessionHasErrors('inventory');
        $this->assertSame(1, $item->current_stock);
        $this->assertNull($addition->fresh()->cancelled_at);
        $this->assertDatabaseCount('inventory_audits', 0);
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Cancel incorrect withdrawal'])->assertSessionHasNoErrors();
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Cancel incorrect restock'])->assertSessionHasNoErrors();
        $this->assertSame(2, $item->current_stock);
        $this->assertSame(2, $item->total_in);
        $this->assertDatabaseCount('additions', 1);
        $this->assertDatabaseCount('outs', 1);
    }

    public function test_archive_requires_zero_stock_preserves_history_and_can_be_restored(): void
    {
        $item = $this->item();
        $this->admin();
        $this->delete('/storage/item/'.$item->id, ['reason' => 'Archive positive stock'])->assertSessionHasErrors('inventory');
        $out = $item->outs()->create(['quantity' => 10, 'date' => now()->subDay()]);
        $this->delete('/storage/item/'.$item->id, ['reason' => 'No longer stocked'])->assertSessionHasNoErrors();
        $this->assertNotNull($item->fresh()->archived_at);
        $this->assertDatabaseCount('product_ins', 1);
        $this->assertDatabaseCount('outs', 1);
        $this->get('/storage?search=History')->assertOk()->assertDontSee('data-name="History item"', false);
        $this->get('/storage?include_archived=1&search=History')->assertOk()->assertSee('data-name="History item"', false);
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()->subDay()->toDateTimeString()])->assertSessionHasErrors('inventory');
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Try cancelled on archive'])->assertSessionHasErrors('inventory');
        $this->post('/storage/item/'.$item->id.'/restore')->assertSessionHasErrors('reason');
        $this->post('/storage/item/'.$item->id.'/restore', ['reason' => 'Stocking item again'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->archived_at);
        $this->assertSame(['archived', 'restored'], InventoryAudit::orderBy('id')->pluck('action')->all());
        $this->get('/audits?product_in_id='.$item->id)->assertOk()->assertSee('No longer stocked')->assertSee('Stocking item again');
    }

    public function test_export_uses_active_movements_and_excludes_archived_items(): void
    {
        $item = $this->item();
        $out = $item->outs()->create(['quantity' => 3, 'date' => now()->subDay()]);
        $addition = $item->additions()->create(['quantity' => 5, 'date' => now()->subDay()]);
        $archived = $this->item(1);
        $archived->update(['name' => 'Archived unique name']);
        $archived->outs()->create(['quantity' => 1, 'date' => now()->subDay()]);
        $this->admin();
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Cancel withdrawal'])->assertSessionHasNoErrors();
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Cancel restock'])->assertSessionHasNoErrors();
        $this->delete('/storage/item/'.$archived->id, ['reason' => 'Archive exhausted item'])->assertSessionHasNoErrors();
        $content = $this->get('/storage/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('10', $content);
        $this->assertStringNotContainsString('Archived unique name', $content);
    }

    public function test_audit_failure_rolls_back_both_new_records_and_cancellations(): void
    {
        $item = $this->item();
        $out = $item->outs()->create(['quantity' => 2, 'date' => now()->subDay()]);
        $actor = $this->admin();
        $eventName = 'eloquent.creating: '.InventoryAudit::class;
        Event::listen($eventName, fn () => throw new RuntimeException('Simulated audit failure'));
        try {
            foreach (['create', 'cancel', 'edit'] as $action) {
                try {
                    $service = app(InventoryService::class);
                    if ($action === 'create') {
                        $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()], $actor);
                    } elseif ($action === 'cancel') {
                        $service->cancel(Out::class, $out->id, 'Correction', $actor);
                    } else {
                        $service->edit(ProductIn::class, $item->id, ['name' => 'Failed change'], 'Correction', $actor);
                    }
                    $this->fail('Expected the audit insert to fail.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('Simulated audit failure', $exception->getMessage());
                }
            }
        } finally {
            Event::forget($eventName);
        }
        $this->assertDatabaseCount('additions', 0);
        $this->assertNull($out->fresh()->cancelled_at);
        $this->assertSame('History item', $item->fresh()->name);
        $this->assertSame(8, $item->current_stock);
        $this->assertDatabaseCount('inventory_audits', 0);
    }

    public function test_audit_rows_and_inventory_records_cannot_be_deleted_through_models(): void
    {
        $actor = $this->admin();
        $item = app(InventoryService::class)->createItem(['name' => 'Protected item', 'category' => 'Test', 'quantity' => 1, 'added_at' => now()], $actor);
        $audit = InventoryAudit::firstOrFail();
        foreach (['edit audit', 'delete audit', 'delete item'] as $action) {
            try {
                match ($action) {
                    'edit audit' => $audit->update(['reason' => 'Overwrite']),
                    'delete audit' => $audit->delete(),
                    'delete item' => $item->delete(),
                };
                $this->fail('Expected protected history operation to fail.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertDatabaseCount('inventory_audits', 1);
        $this->assertDatabaseCount('product_ins', 1);
        $this->assertNull($audit->fresh()->reason);
    }

    public function test_history_escapes_untrusted_reasons_and_retains_actor_name_after_rename(): void
    {
        $item = $this->item();
        $actor = $this->admin();
        $reason = '<script>alert("reason")</script>';
        $this->put('/storage/item/'.$item->id, ['name' => 'Changed', 'category' => 'Test', 'added_at' => now()->subDay()->toDateTimeString(), 'reason' => $reason])->assertSessionHasNoErrors();
        $oldName = $actor->name;
        $actor->update(['name' => 'Renamed admin']);
        $this->get('/audits?product_in_id='.$item->id)->assertOk()->assertSee($oldName)->assertSee($reason)->assertDontSee($reason, false);
    }
}
