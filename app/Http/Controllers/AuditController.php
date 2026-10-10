<?php

namespace App\Http\Controllers;

use App\Models\InventoryAudit;
use App\Models\ProductIn;
use App\Models\User;
use App\Services\OperationLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    public function __construct(private OperationLog $log) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->query($filters, true);

        return view('audits.index', [
            'filters' => $filters,
            'selectedProduct' => isset($filters['product_in_id']) ? ProductIn::findOrFail($filters['product_in_id']) : null,
            'events' => $this->ordered($query)->with('product:id,name,archived_at,cancelled_at')->paginate(20)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name', 'username', 'is_active']),
            'movementProducts' => ProductIn::whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'products' => ProductIn::orderBy('name')->get(['id', 'name', 'archived_at', 'cancelled_at']),
        ]);
    }

    public function itemCard(ProductIn $product)
    {
        $product->load([
            'outs' => fn ($query) => $query->orderByDesc('date'),
            'additions' => fn ($query) => $query->orderByDesc('date'),
        ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'stock' => $product->current_stock,
            'initialQuantity' => $product->quantity,
            'manufacturer' => $product->manufacturer,
            'model' => $product->model_type,
            'sn' => $product->serial_number,
            'receiver' => $product->reciever,
            'date' => $product->added_at->format('Y-m-d\TH:i'),
            'desc' => $product->description,
            'recordedBy' => $product->recorded_by_label,
            'recordedAt' => $product->recorded_at_display,
            'archived' => $product->archived_at !== null,
            'cancelled' => $product->cancelled_at !== null,
            'canCorrect' => $product->can_correct,
            'canReplace' => $product->can_replace,
            'cancellationReason' => $product->cancellation_reason,
            'outs' => $product->outs,
            'additions' => $product->additions,
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request)
    {
        $query = $this->query($this->filters($request))->with('product:id,name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'رقم التدقيق', 'رقم الصنف', 'اسم الصنف الحالي', 'نوع العملية', 'الإجراء', 'رقم السجل',
                'رقم المستخدم', 'المستخدم عند التسجيل', 'وقت التسجيل ('.config('app.timezone').')',
                'تاريخ العملية', 'الكمية', 'المصدر', 'الوجهة', 'الملاحظات', 'سبب التغيير',
                'الرصيد قبل', 'الرصيد بعد', 'تغير الرصيد', 'القيم قبل (JSON)', 'القيم بعد (JSON)', 'مصدر البيانات',
            ], ',', '"', '');
            // Keep one MySQL read snapshot across batches while operators record new movements.
            DB::transaction(function () use ($query, $out) {
                foreach ($this->ordered($query)->lazy(500) as $event) {
                    $values = $event->after_values;
                    $row = [
                        $event->is_legacy ? '' : $event->id, $event->product_in_id, $event->product?->name ?? 'غير متاح', $event->recordLabel(),
                        InventoryAudit::ACTIONS[$event->action] ?? $event->action, $event->record_id,
                        $event->actor_id, $event->actor_name, $event->created_at->format('Y-m-d H:i:s'),
                        $values['date'] ?? $values['added_at'] ?? '', $values['quantity'] ?? '',
                        $values['source'] ?? '', $values['destination'] ?? '', $values['note'] ?? '', $event->reason ?? '',
                        $event->stock_before, $event->stock_after, $event->is_legacy ? '' : $event->stock_after - $event->stock_before,
                        $event->before_values === null ? '' : json_encode($event->before_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $event->is_legacy ? 'سجل سابق — بيانات العملية الحالية' : 'سجل تدقيق محفوظ',
                    ];
                    // Spreadsheet apps must treat user-entered text as text, not formulas.
                    $row = array_map(static fn ($value) => is_string($value) && preg_match('/^(?:\s*[=+\-@]|[\t\r\n])/u', $value) ? "'".$value : $value, $row);
                    fputcsv($out, $row, ',', '"', '');
                }
            });
            fclose($out);
        }, 'operations-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function item(int $id)
    {
        $product = ProductIn::findOrFail($id);

        return redirect()->route('audits.index', ['product_in_id' => $product->id]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'actor_id' => ['nullable', ...($request->input('actor_id') === 'unknown' ? [Rule::in(['unknown'])] : ['integer', Rule::exists('users', 'id')])],
            'product_in_id' => ['nullable', 'integer', Rule::exists('product_ins', 'id')],
            'action' => ['nullable', Rule::in(array_keys(InventoryAudit::ACTIONS))],
            'record_type' => ['nullable', Rule::in(array_keys(InventoryAudit::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ], ['to.after_or_equal' => 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.'], [
            'actor_id' => 'المستخدم', 'product_in_id' => 'الصنف', 'action' => 'الإجراء',
            'record_type' => 'نوع العملية', 'from' => 'تاريخ البداية', 'to' => 'تاريخ النهاية',
        ]);
    }

    private function query(array $filters, bool $compactItemCorrections = false): Builder
    {
        $query = $this->log->query($compactItemCorrections);
        foreach (['actor_id', 'product_in_id', 'action', 'record_type'] as $field) {
            if (isset($filters[$field])) {
                $field === 'actor_id' && $filters[$field] === 'unknown'
                    ? $query->whereNull($field) : $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', Carbon::createFromFormat('!Y-m-d', $filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where('created_at', '<', Carbon::createFromFormat('!Y-m-d', $filters['to'])->addDay()->startOfDay());
        }

        return $query;
    }

    private function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderBy('is_legacy')->orderByDesc('id')->orderBy('record_type');
    }
}
