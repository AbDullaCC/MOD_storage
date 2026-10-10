<?php

namespace Tests\Feature;

use App\Models\Addition;
use App\Models\InventoryAudit;
use App\Models\Out;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditScreenTest extends TestCase
{
    use RefreshDatabase;

    private function item(array $attributes = []): ProductIn
    {
        $item = ProductIn::create([...['name' => 'Audit item', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()->subMonth()], ...$attributes]);
        $item->forceFill(['created_at' => '2025-08-01 00:00:00'])->save();
        if (isset($attributes['archived_at'])) {
            $item->forceFill(['archived_at' => $attributes['archived_at']])->save();
        }

        return $item;
    }

    private function event(ProductIn $item, User $actor, array $attributes = []): InventoryAudit
    {
        return InventoryAudit::create([
            'product_in_id' => $item->id, 'record_type' => 'out', 'record_id' => 7,
            'action' => 'created', 'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'reason' => null, 'before_values' => null,
            'after_values' => ['quantity' => 3, 'date' => '2025-09-01 10:00:00', 'destination' => 'Original office', 'note' => 'Delivery'],
            'stock_before' => 10, 'stock_after' => 7, 'created_at' => '2026-10-08 12:00:00',
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    private function csv(string $url): array
    {
        $response = $this->get($url)->assertOk()->assertDownload()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_audit_screen_and_export_require_an_active_admin(): void
    {
        foreach (['/audits', '/audits/export'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->create());
        $this->get('/storage')->assertOk()->assertDontSee('href="'.route('audits.index').'"', false);
        foreach (['/audits', '/audits/export?actor_id=1'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $disabled = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        foreach (['/audits', '/audits/export'] as $url) {
            $this->actingAs($disabled)->get($url)->assertRedirect('/login');
        }
        $this->admin();
        $this->get('/storage')->assertSee('href="'.route('audits.index').'"', false);
        $this->get('/audits')->assertOk()->assertSee('لا توجد عمليات مطابقة');
        $this->assertCount(1, $this->csv('/audits/export'));
    }

    public function test_screen_shows_all_event_types_saved_names_snapshots_and_archived_items(): void
    {
        $this->admin();
        $actor = User::factory()->create(['name' => 'Original recorder']);
        $item = $this->item(['archived_at' => now()]);
        $events = collect();
        foreach (['created', 'edited', 'cancelled', 'archived', 'restored'] as $action) {
            $events->push($this->event($item, $actor, [
                'action' => $action, 'record_type' => in_array($action, ['archived', 'restored']) ? 'item' : 'out',
                'before_values' => $action === 'created' ? null : ['quantity' => 3, 'destination' => 'Earlier office'],
                'reason' => $action === 'created' ? null : 'Correction reason',
            ]));
        }
        $events->push($this->event($item, $actor, ['record_type' => 'addition']));
        $actor->forceFill(['name' => 'Renamed recorder', 'is_active' => false])->save();
        $response = $this->get('/audits')->assertOk()->assertSee('Original recorder')->assertSee('Earlier office')
            ->assertSee('Original office')->assertSee('Correction reason')->assertSee('مؤرشف')->assertSee('معطّل');
        $this->assertSame($events->pluck('id')->reverse()->values()->all(), $response->viewData('events')->reject(fn ($event) => $event->is_legacy)->pluck('id')->all());
        $this->assertSame(7, $response->viewData('events')->total());
        $this->assertStringContainsString(route('audits.itemCard', $item->id), $response->getContent());
    }

    public function test_rows_explain_stock_effects_and_corrections_without_opening_details(): void
    {
        $admin = $this->admin();
        $service = app(InventoryService::class);
        $item = $service->createItem(['name' => 'DVR', 'category' => 'Test', 'quantity' => 10, 'added_at' => now()], $admin);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now(), 'source' => 'Supplier'], $admin);
        $out = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now(), 'destination' => 'Office'], $admin);
        $service->edit(Out::class, $out->id, ['destination' => 'Warehouse'], 'Correct destination', $admin);
        $service->cancel(Out::class, $out->id, 'Duplicate withdrawal', $admin);
        $service->cancel(Addition::class, $addition->id, 'Duplicate addition', $admin);

        $response = $this->get('/audits')->assertOk();
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        $expected = [
            ['إنشاء صنف جديد', 'green', 0, 10],
            ['إضافة كمية', 'green', 10, 15],
            ['سحب كمية', 'red', 15, 13],
            ['تعديل الوجهة', 'amber', 13, 13],
            ['إلغاء سحب · إعادة للمخزون', 'green', 13, 15],
            ['إلغاء إضافة · خصم من المخزون', 'red', 15, 10],
        ];
        foreach (InventoryAudit::orderBy('id')->get() as $index => $event) {
            [$title, $tone, $before, $after] = $expected[$index];
            $group = '//tbody[@data-audit-event="'.$event->id.'"]';
            $row = $group.'/tr[1]';
            $this->assertStringContainsString($title, $xpath->evaluate('string('.$row.'/td[1])'));
            $this->assertStringContainsString('audit-tone-'.$tone, $xpath->evaluate('string('.$group.'/@class)'));
            $this->assertSame('DVR', $xpath->evaluate('string('.$row.'/td[2]/a)'));
            if ($before !== $after) {
                $this->assertSame('الرصيد قبل '.$before.' وبعد '.$after, $xpath->evaluate('string('.$row.'/td[3]/div/@aria-label)'));
            } else {
                $this->assertSame(0, $xpath->query($row.'/td[3]/div')->length);
                $this->assertStringContainsString('دون تغيير', $xpath->evaluate('string('.$row.'/td[3])'));
            }
            if ($event->action === 'edited') {
                $text = $xpath->evaluate('string('.$row.'/td[1])');
                $this->assertStringContainsString('Office', $text);
                $this->assertStringContainsString('Warehouse', $text);
                $this->assertStringContainsString('دون تغيير', $xpath->evaluate('string('.$row.'/td[3])'));
            } else {
                $this->assertStringContainsString((string) $event->after_values['quantity'], $xpath->evaluate('string('.$row.'/td[1])'));
            }
            $this->assertSame('false', $xpath->evaluate('string('.$row.'/td[6]/button/@aria-expanded)'));
            $this->assertSame(1, $xpath->query($group.'/tr[2][@hidden]')->length);
        }
    }

    public function test_details_keep_business_data_and_only_compare_meaningful_changes(): void
    {
        $admin = $this->admin();
        $service = app(InventoryService::class);
        $item = $service->createItem(['name' => 'Detailed item', 'category' => 'Test', 'quantity' => 10, 'added_at' => '2025-09-01 10:00:00', 'reciever' => 'Receiver', 'description' => 'Item description'], $admin);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 5, 'date' => '2025-09-02 11:30:00', 'source' => 'Supplier', 'note' => 'Addition note'], $admin);
        $out = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => '2025-09-03 12:15:00', 'destination' => 'Office', 'note' => "Withdrawal note\nsecond line"], $admin);
        $service->edit(Out::class, $out->id, ['destination' => 'Warehouse', 'date' => '2025-09-04 09:00:00'], 'Correct destination and date', $admin);
        $service->cancel(Out::class, $out->id, 'Duplicate withdrawal', $admin);
        $service->cancel(Addition::class, $addition->id, 'Duplicate addition', $admin);
        $events = InventoryAudit::orderBy('id')->get();
        $savedSnapshots = $events->map(fn ($event) => [$event->before_values, $event->after_values])->all();

        $response = $this->get('/audits')->assertOk();
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        foreach ($events as $event) {
            $group = '//tbody[@data-audit-event="'.$event->id.'"]';
            $details = $group.'/tr[2]';
            $data = $details.'//section[@aria-label="بيانات العملية"]';
            $comparison = $details.'//table[@aria-label="مقارنة بيانات العملية"]';
            $this->assertSame(1, $xpath->query($data.'//dl')->length);
            $this->assertSame((string) $event->after_values['quantity'], $xpath->evaluate('string('.$data.'//div[dt="الكمية"]/dd)'));
            $detailText = $xpath->evaluate('string('.$details.')');
            foreach (['رقم السجل', 'رقم الصنف', 'رقم المستخدم الأصلي', 'المستخدم الأصلي', 'وقت التسجيل الأصلي', 'وقت التحديث', 'وقت الإلغاء', 'رقم مستخدم الإلغاء', 'ألغيت بواسطة', 'وقت الأرشفة'] as $label) {
                $this->assertStringNotContainsString($label, $detailText);
            }
            $this->assertDoesNotMatchRegularExpression('/\d{2}:\d{2}:\d{2}/', $detailText);
            if ($event->record_type === 'item') {
                $this->assertSame('Receiver', $xpath->evaluate('string('.$data.'//div[dt="المستلم"]/dd)'));
                $this->assertSame('Item description', $xpath->evaluate('string('.$data.'//div[dt="الوصف"]/dd)'));
            } elseif ($event->record_type === 'addition') {
                $this->assertSame('Supplier', $xpath->evaluate('string('.$data.'//div[dt="المصدر"]/dd)'));
                $this->assertSame('Addition note', $xpath->evaluate('string('.$data.'//div[dt="ملاحظات"]/dd)'));
            } else {
                $this->assertSame("Withdrawal note\nsecond line", $xpath->evaluate('string('.$data.'//div[dt="ملاحظات"]/dd)'));
                $this->assertSame(1, $xpath->query($data.'//div[dt="تاريخ العملية"]')->length);
            }
            if ($event->action === 'edited') {
                $this->assertSame(0, $xpath->query($comparison)->length);
                $this->assertSame('Warehouse', $xpath->evaluate('string('.$data.'//div[dt="الوجهة"]/dd)'));
                $this->assertStringContainsString('Office', $xpath->evaluate('string('.$group.'/tr[1])'));
                $this->assertSame('2025-09-04', $xpath->evaluate('string('.$data.'//div[dt="تاريخ العملية"]/dd)'));
                $this->assertSame(0, $xpath->query($details.'//table[@aria-label="رصيد المخزون قبل العملية وبعدها"]')->length);
            } elseif ($event->action === 'cancelled') {
                $this->assertSame(0, $xpath->query($comparison)->length);
            } else {
                $this->assertSame(0, $xpath->query($comparison)->length);
            }
        }
        $this->assertSame($savedSnapshots, InventoryAudit::orderBy('id')->get()->map(fn ($event) => [$event->before_values, $event->after_values])->all());
    }

    public function test_item_card_requires_an_active_admin_and_shows_current_item_data_safely(): void
    {
        $item = $this->item(['name' => '<script>item()</script>', 'manufacturer' => 'Maker', 'model_type' => 'Model X', 'serial_number' => 'SERIAL-001', 'reciever' => 'Receiver', 'description' => '<img src=x onerror=alert(1)>']);
        $item->additions()->create(['quantity' => 5, 'date' => now()]);
        $item->outs()->create(['quantity' => 3, 'date' => now()]);
        $url = route('audits.itemCard', $item->id);
        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->get($url)->assertRedirect('/login');
        $this->admin();
        $response = $this->getJson($url)->assertOk()->assertJsonPath('name', $item->name)
            ->assertJsonPath('desc', $item->description)->assertJsonPath('stock', 12)
            ->assertJsonPath('manufacturer', 'Maker')->assertJsonPath('model', 'Model X')
            ->assertJsonPath('sn', 'SERIAL-001')->assertJsonPath('receiver', 'Receiver')
            ->assertJsonPath('archived', false)->assertJsonCount(1, 'outs')->assertJsonCount(1, 'additions');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $item->forceFill(['archived_at' => now()])->save();
        $this->getJson($url)->assertOk()->assertJsonPath('archived', true);
        $this->get('/audits/items/999999')->assertNotFound();
        $this->get('/audits/items/invalid')->assertNotFound();
    }

    public function test_audit_item_card_has_complete_movement_history_and_matches_inventory_card(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $service = app(InventoryService::class);
        $addition = $service->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()->subDay(), 'source' => 'Supplier', 'note' => '<script>unsafe()</script>'], $admin);
        $olderOut = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 2, 'date' => now()->subDay(), 'destination' => 'Office'], $admin);
        $newerOut = $service->createMovement(Out::class, ['product_in_id' => $item->id, 'quantity' => 3, 'date' => now()], $admin);
        $service->cancel(Out::class, $olderOut->id, 'Duplicate movement', $admin);

        $this->getJson(route('audits.itemCard', $item))->assertOk()
            ->assertJsonPath('stock', 12)->assertJsonPath('outs.0.id', $newerOut->id)
            ->assertJsonPath('outs.1.id', $olderOut->id)->assertJsonPath('outs.1.cancellation_reason', 'Duplicate movement')
            ->assertJsonPath('outs.1.cancelled_by_name', $admin->name)
            ->assertJsonPath('additions.0.id', $addition->id)->assertJsonPath('additions.0.source', 'Supplier')
            ->assertJsonPath('additions.0.note', '<script>unsafe()</script>')->assertJsonPath('additions.0.recorded_by_label', $admin->name);

        $cards = [];
        foreach (['/storage', '/audits'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('modal-additions-body', false)
                ->assertSee('modal-history-body', false)->assertSee('edit-out-form', false)->assertSee('cancel-form', false);
            $document = new \DOMDocument;
            $previousErrors = libxml_use_internal_errors(true);
            try {
                $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previousErrors);
            }
            $xpath = new \DOMXPath($document);
            $this->assertSame(0, $xpath->query('//*[@id="details-modal"]/ancestor::*[@data-page-content]')->length);
            $cards[] = $document->saveHTML($xpath->query('//*[@id="details-modal"]')->item(0));
        }
        $this->assertSame($cards[0], $cards[1]);
    }

    public function test_filters_combine_and_export_uses_the_same_results(): void
    {
        $admin = $this->admin();
        $operator = User::factory()->create();
        $item = $this->item();
        $otherItem = $this->item(['name' => 'Other item']);
        $matching = $this->event($item, $operator, ['action' => 'edited']);
        $this->event($item, $admin, ['action' => 'edited']);
        $this->event($otherItem, $operator, ['action' => 'edited']);
        $this->event($item, $operator);
        $this->event($item, $operator, ['action' => 'edited', 'record_type' => 'addition']);
        $this->event($item, $operator, ['action' => 'edited', 'created_at' => '2026-10-07 23:59:59']);
        $filter = http_build_query(['actor_id' => $operator->id, 'product_in_id' => $item->id, 'action' => 'edited', 'record_type' => 'out', 'from' => '2026-10-08', 'to' => '2026-10-08']);
        $response = $this->get('/audits?'.$filter)->assertOk();
        $this->assertSame([$matching->id], $response->viewData('events')->pluck('id')->all());
        $this->assertSame(1, $response->viewData('events')->total());
        $rows = $this->csv('/audits/export?'.$filter);
        $this->assertCount(2, $rows);
        $this->assertSame((string) $matching->id, $rows[1][0]);
    }

    public function test_recording_date_range_includes_both_whole_days_and_ignores_entered_operation_date(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $this->event($item, $admin, ['created_at' => '2026-10-07 23:59:59']);
        $first = $this->event($item, $admin, ['created_at' => '2026-10-08 00:00:00']);
        $last = $this->event($item, $admin, ['created_at' => '2026-10-09 23:59:59']);
        $this->event($item, $admin, ['created_at' => '2026-10-10 00:00:00']);
        $response = $this->get('/audits?from=2026-10-08&to=2026-10-09')->assertOk();
        $this->assertSame([$last->id, $first->id], $response->viewData('events')->pluck('id')->all());
        $this->assertSame(3, $this->get('/audits?from=2026-10-08')->viewData('events')->total());
        $this->assertSame(3, $this->get('/audits?to=2026-10-08')->viewData('events')->total());
    }

    public function test_pagination_keeps_filters_and_export_includes_every_matching_page(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $other = $this->item(['name' => 'Excluded item']);
        $this->event($other, $admin);
        $events = collect();
        for ($i = 0; $i < 25; $i++) {
            $events->push($this->event($item, $admin));
        }
        $url = '/audits?product_in_id='.$item->id;
        $first = $this->get($url)->assertOk();
        $this->assertCount(20, $first->viewData('events'));
        $this->assertStringContainsString('product_in_id='.$item->id, $first->viewData('events')->nextPageUrl());
        $last = $this->get($url.'&page=2')->assertOk();
        $this->assertCount(6, $last->viewData('events'));
        $rows = $this->csv('/audits/export?product_in_id='.$item->id.'&page=2');
        $this->assertCount(27, $rows);
        $this->assertSame([...$events->pluck('id')->reverse()->map(fn ($id) => (string) $id)->values()->all(), ''], array_column(array_slice($rows, 1), 0));
    }

    public function test_invalid_filters_are_rejected_on_screen_and_export(): void
    {
        $this->admin();
        foreach ([
            ['actor_id' => 99999], ['product_in_id' => 99999], ['action' => 'delete'], ['record_type' => 'user'],
            ['from' => '2026-02-30'], ['to' => 'bad-date'], ['from' => '2026-10-09', 'to' => '2026-10-08'], ['actor_id' => ['1']],
        ] as $filters) {
            $field = isset($filters['from'], $filters['to']) ? 'to' : array_key_first($filters);
            foreach (['/audits', '/audits/export'] as $url) {
                $this->from('/audits')->get($url.'?'.http_build_query($filters))->assertRedirect('/audits')->assertSessionHasErrors($field);
            }
        }
    }

    public function test_untrusted_text_is_escaped_and_csv_preserves_snapshots_without_executing_formulas(): void
    {
        $this->admin();
        $actor = User::factory()->create(['name' => '=1+1']);
        $item = $this->item(['name' => '@SUM(1,1)']);
        $before = ['destination' => 'Original "office", room 1', 'note' => null, 'quantity' => 3];
        $after = ['destination' => 'New office', 'note' => "=1+1\nsecond line", 'quantity' => 3, 'date' => '2025-09-01 10:00:00'];
        $event = $this->event($item, $actor, ['action' => 'edited', 'reason' => '<script>alert(1)</script>', 'before_values' => $before, 'after_values' => $after]);
        $this->get('/audits')->assertOk()->assertSee($event->reason)->assertDontSee($event->reason, false);
        $rows = $this->csv('/audits/export');
        $this->assertSame("'@SUM(1,1)", $rows[1][2]);
        $this->assertSame("'=1+1", $rows[1][7]);
        $this->assertSame("'=1+1\nsecond line", $rows[1][13]);
        $this->assertSame($event->fresh()->before_values, json_decode($rows[1][18], true));
        $this->assertSame($event->fresh()->after_values, json_decode($rows[1][19], true));
        $this->assertEquals($before, $event->fresh()->before_values);
        $this->assertEquals($after, $event->fresh()->after_values);
        $this->assertDatabaseCount('inventory_audits', 1);
    }

    public function test_old_pages_redirect_to_the_single_log_and_an_archived_item_can_be_restored_there(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $item->outs()->create(['quantity' => 10, 'date' => now()->subDay()]);
        app(InventoryService::class)->archive($item->id, 'No stock remaining', $admin);
        $url = route('audits.index', ['product_in_id' => $item->id]);
        $this->get('/storage/report?show_all=1')->assertRedirect('/audits');
        $this->get('/storage/item/'.$item->id.'/history')->assertRedirect($url);
        $this->get('/storage/item/999999/history')->assertNotFound();
        $this->get('/storage')->assertOk()->assertSee('سجل العمليات')->assertDontSee('التقارير')->assertDontSee('سجل التغييرات');
        $this->get($url)->assertOk()->assertSee('ملخص الصنف')->assertSee('الرصيد الحالي')->assertSee('No stock remaining')
            ->assertSee('action="'.route('storage.restoreItem', $item->id).'"', false)->assertSee('سبب إعادة التفعيل');
        $this->from($url)->post('/storage/item/'.$item->id.'/restore', ['reason' => 'Stocking again'])->assertRedirect($url);
        $this->assertNull($item->fresh()->archived_at);
        $this->get($url)->assertOk()->assertSee('Stocking again')
            ->assertDontSee('action="'.route('storage.restoreItem', $item->id).'"', false);
    }

    public function test_legacy_movements_remain_visible_without_duplicate_creations_or_invented_stock_history(): void
    {
        $admin = $this->admin();
        $operator = User::factory()->create(['name' => 'Earlier operator']);
        $item = $this->item();
        $oldAddition = Addition::createRecorded(['product_in_id' => $item->id, 'quantity' => 5, 'date' => now()->subMonth(), 'source' => 'Earlier supplier'], $operator);
        $oldOut = $item->outs()->create(['quantity' => 3, 'date' => now()->subMonth(), 'destination' => 'Earlier office']);
        $newAddition = app(InventoryService::class)->createMovement(Addition::class, ['product_in_id' => $item->id, 'quantity' => 4, 'date' => now()], $operator);
        app(InventoryService::class)->cancel(Out::class, $oldOut->id, 'Cancelled old withdrawal', $admin);
        $response = $this->get('/audits?product_in_id='.$item->id)->assertOk()->assertSee('سجل سابق')->assertSee('ملغاة')
            ->assertSee('Earlier operator')->assertSee('Earlier supplier')->assertSee('Earlier office')->assertSee('Cancelled old withdrawal');
        $entries = collect($response->viewData('events')->items());
        $legacy = $entries->filter(fn ($event) => $event->is_legacy);
        $this->assertCount(5, $entries);
        $this->assertCount(3, $legacy);
        $this->assertEqualsCanonicalizing(['item:'.$item->id, 'addition:'.$oldAddition->id, 'out:'.$oldOut->id], $legacy->map(fn ($event) => $event->record_type.':'.$event->record_id)->all());
        $this->assertSame(1, $entries->filter(fn ($event) => $event->record_type === 'addition' && $event->record_id === $newAddition->id)->count());
        foreach ($legacy as $event) {
            $this->assertNull($event->stock_before);
            $this->assertNull($event->stock_after);
            $this->assertNull($event->before_values);
        }
        $this->assertNotNull($legacy->firstWhere('record_type', 'out')->after_values['cancelled_at']);
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//tbody[@data-legacy-record]//table[@aria-label="رصيد المخزون قبل العملية وبعدها"]')->length);
        $unknown = $this->get('/audits?actor_id=unknown')->assertOk();
        $this->assertSame(2, $unknown->viewData('events')->total());
        $this->assertSame(2, $this->get('/audits?actor_id='.$operator->id)->viewData('events')->total());
        $rows = $this->csv('/audits/export?product_in_id='.$item->id);
        $this->assertCount(6, $rows);
        $legacyRows = array_filter(array_slice($rows, 1), fn ($row) => $row[20] === 'سجل سابق — بيانات العملية الحالية');
        $this->assertCount(3, $legacyRows);
        foreach ($legacyRows as $row) {
            foreach ([0, 15, 16, 17, 18] as $column) {
                $this->assertSame('', $row[$column]);
            }
        }
        $this->assertDatabaseCount('inventory_audits', 2);
    }

    public function test_large_filtered_export_includes_all_batches_and_legacy_records(): void
    {
        $admin = $this->admin();
        $item = $this->item();
        $template = $this->event($item, $admin)->getAttributes();
        unset($template['id']);
        DB::table('inventory_audits')->insert(array_fill(0, 599, $template));
        $rows = $this->csv('/audits/export?product_in_id='.$item->id.'&page=30');
        $this->assertCount(602, $rows);
        $ids = array_column(array_slice($rows, 1, 600), 0);
        $this->assertCount(600, array_unique($ids));
        $this->assertSame(InventoryAudit::orderByDesc('id')->pluck('id')->map(fn ($id) => (string) $id)->all(), $ids);
        $this->assertSame('', $rows[601][0]);
        $this->assertSame('سجل سابق — بيانات العملية الحالية', $rows[601][20]);
    }
}
