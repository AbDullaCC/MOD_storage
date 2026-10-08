<?php

namespace Tests\Feature;

use App\Models\Addition;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationAttributionTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [['operator'], ['admin']];
    }

    private function createOperations(User $actor, array $extra = []): array
    {
        $this->travelTo(now()->startOfSecond());
        $this->actingAs($actor);
        $date = now()->subDays(3)->toDateTimeString();
        $this->post('/storage', array_merge([
            'name' => 'Attribution item', 'category' => 'Test', 'quantity' => 10,
            'added_at' => $date, 'reciever' => 'Warehouse receiver',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
        $item = ProductIn::latest('id')->firstOrFail();
        $this->post('/storage/addition', array_merge([
            'product_in_id' => $item->id, 'quantity' => 5, 'date' => $date,
            'source' => 'Supplier', 'note' => 'Delivery',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/storage/out', array_merge([
            'product_in_id' => $item->id, 'quantity' => 3, 'date' => $date,
            'destination' => 'Office', 'note' => 'Requested equipment',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return [$item, Addition::latest('id')->firstOrFail(), Out::latest('id')->firstOrFail()];
    }

    #[DataProvider('roles')]
    public function test_each_operation_records_the_authenticated_user_and_server_time_despite_forged_fields(string $role): void
    {
        $this->travelTo(now()->startOfSecond());
        $actor = User::factory()->create(['role' => $role]);
        $other = User::factory()->create();
        $records = $this->createOperations($actor, [
            'created_by' => $other->id, 'created_by_name' => 'Forged user',
            'created_at' => '2001-01-01 00:00:00', 'recorded_by_label' => 'Forged label',
        ]);
        foreach ($records as $record) {
            $this->assertSame($actor->id, $record->created_by);
            $this->assertSame($actor->name, $record->created_by_name);
            $this->assertTrue($record->creator->is($actor));
            $this->assertTrue($record->created_at->equalTo(now()));
            $operationDate = $record instanceof ProductIn ? $record->added_at : $record->date;
            $this->assertTrue($operationDate->equalTo(now()->subDays(3)));
        }
        $this->assertSame(12, $records[0]->current_stock);
        $this->assertSame('Warehouse receiver', $records[0]->reciever);
        $this->assertSame('Supplier', $records[1]->source);
        $this->assertSame('Office', $records[2]->destination);
    }

    public function test_restocks_and_withdrawals_belong_to_their_own_operator_not_the_item_creator(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $item = ProductIn::createRecorded(['name' => 'Shared item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()], $owner);
        $this->actingAs($operator);
        foreach (['/storage/addition', '/storage/out'] as $path) {
            $this->post($path, ['product_in_id' => $item->id, 'quantity' => 1, 'date' => now()->subMinute()->toDateTimeString()])->assertSessionHasNoErrors();
        }
        $this->assertSame($owner->id, $item->fresh()->created_by);
        $this->assertSame($operator->id, $item->additions()->firstOrFail()->created_by);
        $this->assertSame($operator->id, $item->outs()->firstOrFail()->created_by);
    }

    public function test_later_edits_and_account_renames_preserve_original_attribution(): void
    {
        $actor = User::factory()->create(['name' => 'Original operator']);
        $records = $this->createOperations($actor);
        $recordedAt = $records[0]->created_at->toDateTimeString();
        $actor->update(['name' => 'Renamed operator']);
        $actor->forceFill(['is_active' => false])->save();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->travel(1)->hours();
        foreach ($records as $index => $record) {
            $resource = ['item', 'addition', 'out'][$index];
            $payload = [
                'name' => 'Changed item', 'category' => 'Test', 'added_at' => now()->subDay()->toDateTimeString(),
                'date' => now()->subDay()->toDateTimeString(), 'note' => 'Corrected',
                'reason' => 'Correct operation details',
                'created_by' => $admin->id, 'created_by_name' => 'Forged replacement', 'created_at' => now()->toDateTimeString(),
            ];
            $this->put('/storage/'.$resource.'/'.$record->id, $payload)->assertSessionHasNoErrors()->assertRedirect();
            $record->refresh();
            $this->assertSame($actor->id, $record->created_by);
            $this->assertSame('Original operator', $record->recorded_by_label);
            $this->assertSame($recordedAt, $record->created_at->toDateTimeString());
        }
        $this->get('/storage/report?show_all=1')->assertOk()->assertSee('Original operator')->assertDontSee('Renamed operator');
    }

    public function test_report_and_item_details_expose_recording_time_and_names_safely(): void
    {
        $actor = User::factory()->create(['name' => '<script>alert("operator")</script>']);
        [$item] = $this->createOperations($actor);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/storage')
            ->assertOk()->assertSee('data-recorded-by="'.e($actor->name).'"', false)
            ->assertSee('id="modal-recorded-at"', false)->assertDontSee($actor->name, false);
        $response = $this->get('/storage/report?show_all=1')->assertOk()
            ->assertSee($actor->name)->assertDontSee($actor->name, false)
            ->assertSee($item->recorded_at_display)->assertSee('تاريخ العملية')->assertSee('وقت التسجيل');
        $rows = collect($response->viewData('transactions')->items());
        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(['إنشاء صنف جديد', 'إضافة كمية', 'سحب'], $rows->pluck('action_label')->all());
        $this->assertSame([$actor->name], $rows->pluck('recorded_by')->unique()->values()->all());
        $this->assertSame([$item->recorded_at_display], $rows->pluck('recorded_at')->unique()->values()->all());
    }

    public function test_imported_legacy_data_remains_unattributed_and_preserves_original_dates(): void
    {
        $stamp = '2025-12-15 10:20:30';
        $itemId = DB::table('product_ins')->insertGetId([
            'name' => 'Legacy item', 'category' => 'Test', 'quantity' => 10,
            'added_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp,
        ]);
        foreach (['additions', 'outs'] as $table) {
            DB::table($table)->insert(['product_in_id' => $itemId, 'quantity' => 2, 'date' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp]);
        }
        foreach ([ProductIn::firstOrFail(), Addition::firstOrFail(), Out::firstOrFail()] as $record) {
            $this->assertNull($record->created_by);
            $this->assertNull($record->created_by_name);
            $this->assertSame($stamp, $record->recorded_at_display);
            $this->assertSame('سجل سابق / المستخدم غير معروف', $record->recorded_by_label);
        }
        $this->assertSame(10, ProductIn::firstOrFail()->current_stock);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/storage/report?show_all=1')->assertOk()->assertSee('سجل سابق / المستخدم غير معروف');
        $this->get('/storage')->assertOk()->assertSee('سجل سابق / المستخدم غير معروف');
        $this->put('/storage/item/'.$itemId, ['name' => 'Edited legacy item', 'category' => 'Test', 'added_at' => $stamp, 'reason' => 'Correct legacy name'])
            ->assertSessionHasNoErrors();
        $this->assertNull(ProductIn::findOrFail($itemId)->created_by);
        $this->assertSame($stamp, ProductIn::findOrFail($itemId)->recorded_at_display);
    }

    public function test_referenced_accounts_cannot_be_deleted_from_mysql(): void
    {
        $actor = User::factory()->create();
        $this->createOperations($actor);
        foreach (['product_ins', 'additions', 'outs'] as $table) {
            $foreignKeys = DB::select(
                'SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ?',
                [$table]
            );
            $this->assertTrue(collect($foreignKeys)->contains(fn ($key) => $key->COLUMN_NAME === 'created_by' && $key->REFERENCED_TABLE_NAME === 'users' && $key->DELETE_RULE === 'RESTRICT'));
        }
        $this->expectException(QueryException::class);
        $actor->delete();
    }

    public function test_rejected_operations_do_not_create_attributed_records(): void
    {
        $actor = User::factory()->create();
        [$item] = $this->createOperations($actor);
        $this->post('/storage', ['name' => 'Invalid item'])->assertSessionHasErrors();
        $this->post('/storage/addition', ['product_in_id' => $item->id, 'quantity' => 0, 'date' => now()->toDateTimeString()])->assertSessionHasErrors();
        $this->post('/storage/out', ['product_in_id' => $item->id, 'quantity' => 1000, 'date' => now()->toDateTimeString()])->assertSessionHasErrors('inventory');
        $this->assertDatabaseCount('product_ins', 1);
        $this->assertDatabaseCount('additions', 1);
        $this->assertDatabaseCount('outs', 1);
    }
}
