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
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EmployeeCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private function item(User $actor, array $attributes = []): ProductIn
    {
        return app(InventoryService::class)->createItem([
            'name' => 'Employee item', 'category' => 'Test', 'quantity' => 10,
            'added_at' => now()->subDay(), ...$attributes,
        ], $actor);
    }

    private function itemData(array $attributes = []): array
    {
        return ['name' => 'Corrected item', 'category' => 'Test', 'quantity' => 8, 'added_at' => now()->subDay()->toDateTimeString(), 'reason' => 'Incorrect initial entry', ...$attributes];
    }

    public function test_employee_can_edit_own_item_details_without_replacing_it_or_changing_creator(): void
    {
        $operator = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->item($operator);
        $this->actingAs($operator)->put('/storage/item/'.$item->id, $this->itemData(['quantity' => 10, 'created_by' => $other->id]))->assertSessionHasNoErrors();
        $this->assertSame('Corrected item', $item->fresh()->name);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame($operator->id, $item->fresh()->created_by);
        $audit = InventoryAudit::orderByDesc('id')->firstOrFail();
        $this->assertSame('Employee item', $audit->before_values['name']);
        $this->assertSame('Corrected item', $audit->after_values['name']);
        $this->assertSame(10, $audit->stock_before);
        $this->assertSame(10, $audit->stock_after);
        $this->assertSame($operator->id, $audit->actor_id);
        $this->assertSame('Incorrect initial entry', $audit->reason);
    }

    public function test_employee_can_correct_own_movements_on_another_employees_item(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $item = $this->item($owner);
        $service = app(InventoryService::class);
        $out = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now()->subDay()], $operator);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now()->subDay()], $operator);
        $this->actingAs($operator)->put('/storage/out/'.$out->id, ['destination' => 'Correct office', 'date' => now()->subDay()->toDateTimeString(), 'reason' => 'Correct destination', 'quantity' => 3, 'product_in_id' => $item->id])->assertSessionHasNoErrors();
        $this->put('/storage/addition/'.$addition->id, ['source' => 'Correct supplier', 'date' => now()->subDay()->toDateTimeString(), 'reason' => 'Correct source'])->assertSessionHasNoErrors();
        $this->assertSame(3, $out->fresh()->quantity);
        $this->assertSame('Correct office', $out->fresh()->destination);
        $this->assertSame('Correct supplier', $addition->fresh()->source);
        $this->assertSame(9, $item->fresh()->current_stock);
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Duplicate withdrawal'])->assertSessionHasNoErrors();
        $this->assertSame(12, $item->fresh()->current_stock);
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Duplicate addition'])->assertSessionHasNoErrors();
        $this->assertSame(10, $item->fresh()->current_stock);
        $this->assertSame($operator->id, $out->fresh()->cancelled_by);
        $this->assertSame($operator->name, $addition->fresh()->cancelled_by_name);
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Cannot cancel twice'])->assertSessionHasErrors('inventory');
        $this->assertDatabaseCount('inventory_audits', 7);
    }

    public function test_employee_cannot_change_other_records_or_admin_pages_even_with_direct_requests(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $item = $this->item($owner);
        $out = $item->outs()->create(['quantity' => 1, 'date' => now()]);
        $addition = $item->additions()->create(['quantity' => 2, 'date' => now()]);
        $this->actingAs($operator);
        foreach ([
            ['PUT', '/storage/item/'.$item->id], ['DELETE', '/storage/item/'.$item->id.'/cancel'],
            ['DELETE', '/storage/item/'.$item->id], ['POST', '/storage/item/'.$item->id.'/restore'],
            ['PUT', '/storage/out/'.$out->id], ['DELETE', '/storage/out/'.$out->id],
            ['PUT', '/storage/addition/'.$addition->id], ['DELETE', '/storage/addition/'.$addition->id],
            ['GET', '/audits'], ['GET', '/audits/export'], ['GET', '/users'],
        ] as [$method, $url]) {
            $this->call($method, $url, $this->itemData())->assertForbidden();
        }
        $this->assertDatabaseCount('inventory_audits', 1);
        $this->assertNull($item->fresh()->cancelled_at);
        $this->assertNull($out->fresh()->cancelled_at);
        $this->assertNull($addition->fresh()->cancelled_at);
    }

    public function test_new_correction_routes_require_login(): void
    {
        foreach ([
            ['PUT', '/storage/item/1'], ['DELETE', '/storage/item/1/cancel'],
            ['PUT', '/storage/out/1'], ['PUT', '/storage/addition/1'],
        ] as [$method, $url]) {
            $this->call($method, $url)->assertRedirect('/login');
        }
    }

    public function test_unused_item_replacement_preserves_original_quantity_and_reuses_serial_safely(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator, ['serial_number' => 'SERIAL-001']);
        $this->actingAs($operator);
        $this->put('/storage/item/'.$item->id, $this->itemData(['serial_number' => 'SERIAL-001']))->assertSessionHasNoErrors()->assertRedirect(route('storage.index', ['search' => 'Corrected item']));
        $replacement = ProductIn::where('id', '!=', $item->id)->firstOrFail();
        $original = $item->fresh();
        $this->assertSame(10, $original->quantity);
        $this->assertSame('SERIAL-001', $original->serial_number);
        $this->assertNotNull($original->cancelled_at);
        $this->assertNotNull($original->archived_at);
        $this->assertSame(0, $original->current_stock);
        $this->assertSame(0, $original->total_in);
        $this->assertSame($operator->id, $original->cancelled_by);
        $this->assertSame(8, $replacement->current_stock);
        $this->assertSame('SERIAL-001', $replacement->active_serial_number);
        $audits = InventoryAudit::orderBy('id')->get();
        $this->assertCount(3, $audits);
        $this->assertSame('cancelled', $audits[1]->action);
        $this->assertSame(10, $audits[1]->stock_before);
        $this->assertSame(0, $audits[1]->stock_after);
        $this->assertSame($replacement->id, $audits[1]->after_values['replacement_id']);
        $this->assertSame($item->id, $audits[2]->after_values['replaces_id']);
        $this->assertSame(0, $audits[2]->stock_before);
        $this->assertSame(8, $audits[2]->stock_after);
        $this->get('/storage')->assertDontSee('data-id="'.$item->id.'"', false)->assertSee('data-id="'.$replacement->id.'"', false);
        $this->post('/storage', $this->itemData(['serial_number' => 'SERIAL-001']))->assertSessionHasErrors('serial_number');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/storage/item/'.$item->id.'/restore', ['reason' => 'Cannot restore cancelled item'])->assertSessionHasErrors('inventory');
        $this->get('/audits?product_in_id='.$item->id)->assertSee('ملغى')->assertDontSee('سبب إعادة التفعيل')
            ->assertViewHas('events', fn ($events) => $events->total() === 0);
        $this->get('/storage/export')->assertOk();
    }

    public function test_unused_item_can_be_cancelled_without_replacement_and_cannot_be_used_again(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator);
        $this->actingAs($operator)->delete('/storage/item/'.$item->id.'/cancel', ['reason' => 'Entire entry was incorrect'])->assertSessionHasNoErrors();
        $this->assertSame(0, $item->fresh()->current_stock);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertDatabaseCount('product_ins', 1);
        $this->assertDatabaseCount('inventory_audits', 2);
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()->toDateTimeString()])->assertSessionHasErrors('inventory');
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()->toDateTimeString()])->assertSessionHasErrors('inventory');
        $this->put('/storage/item/'.$item->id, $this->itemData())->assertSessionHasErrors('inventory');
        $this->delete('/storage/item/'.$item->id.'/cancel', ['reason' => 'Cannot cancel twice'])->assertSessionHasErrors('inventory');
        $this->assertDatabaseCount('inventory_audits', 2);
    }

    public static function movementHistory(): array
    {
        return [['out', false], ['out', true], ['addition', false], ['addition', true]];
    }

    #[DataProvider('movementHistory')]
    public function test_any_movement_permanently_blocks_item_cancellation_and_replacement(string $kind, bool $cancelled): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator);
        $class = $kind === 'out' ? Out::class : Addition::class;
        $service = app(InventoryService::class);
        $movement = $service->createMovement($class, ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()], $operator);
        if ($cancelled) {
            $service->cancel($class, $movement->id, 'Wrong movement', $operator);
        }
        foreach ([$operator, User::factory()->create(['role' => 'admin'])] as $actor) {
            $this->actingAs($actor)->delete('/storage/item/'.$item->id.'/cancel', ['reason' => 'Attempt cancellation'])->assertSessionHasErrors('inventory');
            $this->put('/storage/item/'.$item->id, $this->itemData())->assertSessionHasErrors('inventory');
            $this->put('/storage/item/'.$item->id, $this->itemData(['quantity' => 999]))->assertSessionHasErrors('inventory');
            $this->put('/storage/item/'.$item->id, $this->itemData(['quantity' => 10]))->assertSessionHasNoErrors();
        }
        $this->assertNull($item->fresh()->cancelled_at);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertDatabaseCount('product_ins', 1);
    }

    public function test_withdrawal_quantity_requires_cancellation_and_new_entry(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator);
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 7, 'date' => now()->subDay(), 'destination' => 'Old office'], $operator);
        $this->actingAs($operator)->put('/storage/out/'.$out->id, ['quantity' => 5, 'date' => now()->toDateTimeString(), 'reason' => 'Incorrect quantity'])->assertSessionHasErrors('quantity');
        $this->assertNull($out->fresh()->cancelled_at);
        $this->assertSame(3, $item->fresh()->current_stock);
        $this->assertDatabaseCount('inventory_audits', 2);
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Incorrect quantity'])->assertSessionHasNoErrors();
        $this->assertSame(10, $item->fresh()->current_stock);
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()->toDateTimeString(), 'destination' => 'Correct office'])->assertSessionHasNoErrors();
        $this->assertSame(7, $out->fresh()->quantity);
        $this->assertSame(5, $item->fresh()->current_stock);
        $this->assertDatabaseHas('outs', ['quantity' => 5, 'destination' => 'Correct office', 'cancelled_at' => null]);
        $this->assertDatabaseCount('outs', 2);
        $this->assertDatabaseCount('inventory_audits', 4);
    }

    public function test_wrong_item_correction_moves_stock_effect_to_the_selected_item(): void
    {
        $operator = User::factory()->create();
        $source = $this->item($operator);
        $target = $this->item(User::factory()->create(), ['name' => 'Correct product', 'quantity' => 8]);
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $source->id, 'quantity' => 7, 'date' => now()], $operator);
        $this->actingAs($operator)->put('/storage/out/'.$out->id, ['product_in_id' => $target->id, 'date' => now()->toDateTimeString(), 'reason' => 'Incorrect item selected'])->assertSessionHasNoErrors();
        $this->assertSame(10, $source->fresh()->current_stock);
        $this->assertSame(1, $target->fresh()->current_stock);
        $this->assertDatabaseHas('outs', ['product_in_id' => $target->id, 'quantity' => 7, 'created_by' => $operator->id, 'cancelled_at' => null]);
        $audits = InventoryAudit::orderByDesc('id')->take(2)->get()->reverse()->values();
        $this->assertSame([$source->id, $target->id], $audits->pluck('product_in_id')->all());
        $this->assertSame([3, 8], $audits->pluck('stock_before')->all());
        $this->assertSame([10, 1], $audits->pluck('stock_after')->all());
    }

    public function test_addition_quantity_edit_is_rejected_and_cancellation_requires_available_stock(): void
    {
        $operator = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->item($operator);
        $service = app(InventoryService::class);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 10, 'date' => now(), 'source' => 'Supplier'], $operator);
        $out = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 19, 'date' => now()], $operator);
        foreach ([$operator, $admin] as $actor) {
            $this->actingAs($actor)->put('/storage/addition/'.$addition->id, ['quantity' => 9, 'date' => now()->toDateTimeString(), 'reason' => 'Wrong quantity'])->assertSessionHasErrors('quantity');
            $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Wrong quantity'])->assertSessionHasErrors('inventory');
        }
        $this->assertSame(1, $item->fresh()->current_stock);
        $this->assertNull($addition->fresh()->cancelled_at);
        $this->assertDatabaseCount('inventory_audits', 3);
        $this->actingAs($operator)->delete('/storage/out/'.$out->id, ['reason' => 'Wrong withdrawal'])->assertSessionHasNoErrors();
        $this->delete('/storage/addition/'.$addition->id, ['reason' => 'Wrong quantity'])->assertSessionHasNoErrors();
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 9, 'source' => 'Correct supplier', 'date' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->assertSame(19, $item->fresh()->current_stock);
        $this->assertSame(10, $addition->fresh()->quantity);
        $this->assertNotNull($addition->fresh()->cancelled_at);
        $this->assertDatabaseHas('additions', ['quantity' => 9, 'source' => 'Correct supplier', 'cancelled_at' => null]);
    }

    public function test_insufficient_stock_corrections_leave_original_movements_and_audits_unchanged(): void
    {
        $operator = User::factory()->create();
        $source = $this->item($operator);
        $target = $this->item($operator, ['quantity' => 1]);
        $service = app(InventoryService::class);
        $out = $service->createMovement(Out::class, ['product_in_id' => $source->id, 'quantity' => 7, 'date' => now()], $operator);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $source->id, 'quantity' => 10, 'date' => now()], $operator);
        $service->createMovement(Out::class, ['product_in_id' => $source->id, 'quantity' => 12, 'date' => now()], $operator);
        $auditCount = InventoryAudit::count();
        $this->actingAs($operator);
        foreach ([['out', $out], ['addition', $addition]] as [$kind, $movement]) {
            $this->put('/storage/'.$kind.'/'.$movement->id, ['product_in_id' => $target->id, 'date' => now()->toDateTimeString(), 'reason' => 'Attempt invalid correction'])->assertSessionHasErrors('inventory');
            $this->assertNull($movement->fresh()->cancelled_at);
        }
        $this->assertSame(1, $source->fresh()->current_stock);
        $this->assertSame(1, $target->fresh()->current_stock);
        $this->assertDatabaseCount('outs', 2);
        $this->assertDatabaseCount('additions', 1);
        $this->assertDatabaseCount('inventory_audits', $auditCount);
    }

    public function test_replacement_validation_and_required_reasons_leave_originals_active(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator, ['serial_number' => 'ORIGINAL-SERIAL']);
        $other = $this->item($operator, ['serial_number' => 'OTHER-SERIAL']);
        $this->actingAs($operator);
        $this->put('/storage/item/'.$item->id, $this->itemData(['reason' => '']))->assertSessionHasErrors('reason');
        $this->put('/storage/item/'.$item->id, $this->itemData(['quantity' => 0]))->assertSessionHasErrors('quantity');
        $this->put('/storage/item/'.$item->id, $this->itemData(['serial_number' => $other->serial_number]))->assertSessionHasErrors('serial_number');
        $this->delete('/storage/item/'.$item->id.'/cancel', ['reason' => ''])->assertSessionHasErrors('reason');
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now()], $operator);
        $this->put('/storage/out/'.$out->id, ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()->toDateTimeString()])->assertSessionHasErrors('reason');
        $this->assertNull($item->fresh()->cancelled_at);
        $this->assertNull($out->fresh()->cancelled_at);
        $this->assertDatabaseCount('product_ins', 2);
        $this->assertDatabaseCount('outs', 1);
        $this->assertDatabaseCount('inventory_audits', 3);
    }

    public function test_failure_of_second_audit_rolls_back_both_item_and_movement_replacement(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator, ['serial_number' => 'SERIAL-ROLLBACK']);
        $movementItem = $this->item($operator);
        $targetItem = $this->item($operator);
        $service = app(InventoryService::class);
        $out = $service->createMovement(Out::class, ['product_in_id' => $movementItem->id, 'quantity' => 3, 'date' => now()], $operator);
        $eventName = 'eloquent.creating: '.InventoryAudit::class;
        $auditCount = InventoryAudit::count();
        foreach (['item', 'out'] as $kind) {
            $inserts = 0;
            Event::listen($eventName, function () use (&$inserts) {
                if (++$inserts === 2) {
                    throw new RuntimeException('Second audit insert failed');
                }
            });
            try {
                if ($kind === 'item') {
                    $service->updateItem($item->id, $this->itemData(['serial_number' => 'SERIAL-ROLLBACK']), 'Replace incorrect item', $operator);
                } else {
                    $service->updateMovement(Out::class, $out->id, ['product_in_id' => $targetItem->id, 'date' => now()], 'Replace incorrect item', $operator);
                }
                $this->fail('Expected audit failure');
            } catch (RuntimeException $exception) {
                $this->assertSame('Second audit insert failed', $exception->getMessage());
            } finally {
                Event::forget($eventName);
            }
            $this->assertNull($item->fresh()->cancelled_at);
            $this->assertSame('SERIAL-ROLLBACK', $item->fresh()->active_serial_number);
            $this->assertNull($out->fresh()->cancelled_at);
            $this->assertSame(7, $movementItem->fresh()->current_stock);
            $this->assertDatabaseCount('product_ins', 3);
            $this->assertDatabaseCount('outs', 1);
            $this->assertDatabaseCount('inventory_audits', $auditCount);
        }
    }

    public function test_admin_can_correct_other_employees_records_and_attribution_is_preserved(): void
    {
        $operator = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->item($operator);
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now()], $operator);
        $this->actingAs($admin)->put('/storage/item/'.$item->id, $this->itemData(['quantity' => 10]))->assertSessionHasNoErrors();
        $this->delete('/storage/out/'.$out->id, ['reason' => 'Admin correction'])->assertSessionHasNoErrors();
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->assertSame($operator->id, $item->fresh()->created_by);
        $this->assertSame($operator->id, $out->fresh()->created_by);
        $this->assertSame($admin->id, $out->fresh()->cancelled_by);
        $this->assertDatabaseHas('outs', ['quantity' => 2, 'created_by' => $admin->id, 'cancelled_at' => null]);
        $this->get('/audits')->assertOk()->assertSee('سحب كمية')->assertSee('Admin correction');
    }

    public function test_inventory_card_capabilities_match_employee_record_ownership(): void
    {
        $operator = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->item($operator, ['name' => 'Own unused item']);
        $shared = $this->item($other, ['name' => 'Shared item']);
        $ownOut = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $shared->id, 'quantity' => 1, 'date' => now()], $operator);
        $otherOut = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $shared->id, 'quantity' => 1, 'date' => now()], $other);
        $response = $this->actingAs($operator)->get('/storage')->assertOk();
        $document = new \DOMDocument;
        $errors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }
        $xpath = new \DOMXPath($document);
        $this->assertSame('1', $xpath->evaluate('string(//button[@data-id="'.$own->id.'"]/@data-can-correct)'));
        $this->assertSame('1', $xpath->evaluate('string(//button[@data-id="'.$own->id.'"]/@data-can-replace)'));
        $this->assertSame('0', $xpath->evaluate('string(//button[@data-id="'.$shared->id.'"]/@data-can-correct)'));
        $this->assertSame('0', $xpath->evaluate('string(//button[@data-id="'.$shared->id.'"]/@data-can-replace)'));
        $movements = collect(json_decode($xpath->evaluate('string(//button[@data-id="'.$shared->id.'"]/@data-outs)'), true));
        $this->assertTrue($movements->firstWhere('id', $ownOut->id)['can_correct']);
        $this->assertFalse($movements->firstWhere('id', $otherOut->id)['can_correct']);
    }

    public function test_same_item_and_quantity_edits_keep_movement_id_and_preserve_stock(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator);
        $service = app(InventoryService::class);
        foreach ([Out::class => 'out', Addition::class => 'addition'] as $class => $kind) {
            $movement = $service->createMovement($class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now()], $operator);
            $stock = $item->fresh()->current_stock;
            $before = InventoryAudit::count();
            $this->actingAs($operator)->put('/storage/'.$kind.'/'.$movement->id, [
                'product_in_id' => $item->id, 'quantity' => 2, 'date' => now()->toDateTimeString(),
                'note' => 'Updated details', 'reason' => 'Correct note',
            ])->assertSessionHasNoErrors();
            $this->assertNull($movement->fresh()->cancelled_at);
            $this->assertSame('Updated details', $movement->fresh()->note);
            $this->assertSame($stock, $item->fresh()->current_stock);
            $this->assertSame(1, $class::count());
            $this->assertSame($before + 1, InventoryAudit::count());
            $audit = InventoryAudit::latest('id')->firstOrFail();
            $this->assertSame('edited', $audit->action);
            $this->assertSame($movement->id, $audit->record_id);
            $this->assertSame($stock, $audit->stock_before);
            $this->assertSame($stock, $audit->stock_after);
        }
    }

    public function test_changing_only_movement_item_creates_replacement_and_preserves_other_details(): void
    {
        $operator = User::factory()->create();
        $source = $this->item($operator);
        $target = $this->item($operator);
        $out = app(InventoryService::class)->createMovement(Out::class, ['product_in_id' => $source->id, 'quantity' => 3, 'date' => now(), 'destination' => 'Office', 'note' => 'Keep this note'], $operator);
        $this->actingAs($operator)->put('/storage/out/'.$out->id, [
            'product_in_id' => $target->id, 'quantity' => 3, 'date' => $out->date->toDateTimeString(), 'reason' => 'Wrong item selected',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($out->fresh()->cancelled_at);
        $this->assertSame(10, $source->fresh()->current_stock);
        $this->assertSame(7, $target->fresh()->current_stock);
        $this->assertDatabaseHas('outs', ['product_in_id' => $target->id, 'quantity' => 3, 'destination' => 'Office', 'note' => 'Keep this note', 'cancelled_at' => null]);
    }

    public function test_item_quantity_edit_preserves_omitted_details_and_original_creator(): void
    {
        $operator = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->item($operator, ['manufacturer' => 'Original maker', 'serial_number' => 'SAME-SERIAL', 'description' => 'Original description']);
        $this->actingAs($admin)->put('/storage/item/'.$item->id, $this->itemData())->assertSessionHasNoErrors();
        $replacement = ProductIn::where('id', '!=', $item->id)->firstOrFail();
        $this->assertSame('Original maker', $replacement->manufacturer);
        $this->assertSame('SAME-SERIAL', $replacement->serial_number);
        $this->assertSame('Original description', $replacement->description);
        $this->assertSame(8, $replacement->quantity);
        $this->assertSame($admin->id, $replacement->created_by);
        $this->assertSame($operator->id, $item->fresh()->created_by);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame('Employee item', $item->fresh()->name);
    }

    public function test_shared_edit_dialogs_have_full_fields_and_old_correction_pages_are_removed(): void
    {
        $operator = User::factory()->create();
        $item = $this->item($operator);
        $response = $this->actingAs($operator)->get('/storage')->assertOk();
        $response->assertSee('id="edit-item-quantity"', false)->assertSee('id="edit-out-product"', false)
            ->assertDontSee('id="edit-out-quantity"', false)->assertSee('id="edit-addition-product"', false)
            ->assertDontSee('id="edit-addition-quantity"', false)->assertSee('تعديل العملية')
            ->assertDontSee('id="replace-item-link"', false)->assertDontSee('تصحيح العملية</a>', false);
        foreach (['item', 'out', 'addition'] as $kind) {
            $this->get('/storage/'.$kind.'/'.$item->id.'/replace')->assertNotFound();
            $this->post('/storage/'.$kind.'/'.$item->id.'/replace')->assertNotFound();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/audits')->assertOk()
            ->assertSee('id="edit-out-product"', false)->assertDontSee('id="replace-item-link"', false);
        $this->get('/audits/items/'.$item->id)->assertJsonPath('initialQuantity', 10)->assertJsonPath('canReplace', true);
    }
}
