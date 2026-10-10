<?php

namespace Tests\Feature;

use App\Models\InventoryAudit;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\OperationLog;
use Database\Seeders\DemoInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_covers_corrections_and_stock_states_with_real_audits_and_preserved_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'operator']);
        $accounts = DB::table('users')->orderBy('id')->get()->toJson();
        $this->seed(DemoInventorySeeder::class);

        $this->assertSame($accounts, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('product_ins', 18);
        $this->assertDatabaseCount('inventory_audits', 49);
        $expected = [
            'حاسوب محمول Dell Latitude' => 19, 'حبر طابعة HP 59A' => 14,
            'كابل شبكة بطول 3 أمتار' => 30, 'فأرة لاسلكية Logitech' => 26,
            'لوحة مفاتيح عربية' => 14, 'شاشة Samsung مقاس 24 بوصة' => 8,
            'راوتر فرع قديم' => 0, 'مزود طاقة احتياطي UPS' => 4,
            'سويتش شبكة 8 منافذ' => 0, 'قرص تخزين SSD سعة 500 GB' => 1,
            'جهاز عرض Epson' => 3, 'خادم ملفات صغير' => 1,
            'كابل HDMI بطول مترين' => 27, 'كابل USB-C' => 20,
            'كرسي مكتب من السجلات السابقة' => 4,
        ];
        foreach ($expected as $name => $stock) {
            $item = ProductIn::where('name', $name)->whereNull('cancelled_at')->sole();
            $this->assertSame($stock, $item->current_stock, $name);
        }
        $this->assertSame(3, ProductIn::whereNotNull('cancelled_at')->count());
        $this->assertSame(1, ProductIn::whereNotNull('archived_at')->whereNull('cancelled_at')->count());
        $this->assertEqualsCanonicalizing([$admin->id, $employee->id], InventoryAudit::distinct()->pluck('actor_id')->all());
        $this->assertEqualsCanonicalizing(['created', 'edited', 'cancelled', 'archived', 'restored'], InventoryAudit::distinct()->pluck('action')->all());
        $this->assertSame(0, InventoryAudit::where('stock_before', '<', 0)->orWhere('stock_after', '<', 0)->count());
        $this->assertSame(0, InventoryAudit::where('created_at', '>', now())->count());
        $this->assertSame(47, app(OperationLog::class)->query(true)->count());
        $this->assertSame(51, app(OperationLog::class)->query()->count());
        $this->actingAs($admin)->get('/audits')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 47);
        $this->actingAs($employee)->get('/storage')->assertOk();
    }
}
