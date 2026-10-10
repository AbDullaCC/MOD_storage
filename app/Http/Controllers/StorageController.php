<?php

namespace App\Http\Controllers;

use App\Models\Addition;
use App\Models\Out;
use App\Models\ProductIn;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorageController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    // Show the dashboard / list
    public function index(Request $request)
    {
        $query = ProductIn::with([
            'outs' => function ($q) {
                $q->orderBy('date', 'desc');
            },
            'additions' => function ($q) {
                $q->orderBy('date', 'desc');
            },
        ]);

        if (! $request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // CHANGED: get() -> paginate(20)
        // withQueryString() ensures filters stay when you click "Page 2"
        $products = $query->latest('added_at')->paginate(20)->withQueryString();

        return view('storage.index', ['products' => $products, 'movementProducts' => ProductIn::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])]);
    }

    // Export inventory to CSV (Excel-compatible)
    public function export(Request $request)
    {
        $query = ProductIn::whereNull('archived_at')
            ->withSum(['additions as additions_sum' => fn ($q) => $q->whereNull('cancelled_at')], 'quantity')
            ->withSum(['outs as outs_sum' => fn ($q) => $q->whereNull('cancelled_at')], 'quantity');

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $products = $query->latest('added_at')->get();

        // Checkbox: checked by default. Only filter when explicitly set to 0/false.
        $includeOutOfStock = $request->has('include_out_of_stock')
            ? $request->boolean('include_out_of_stock')
            : true;

        $rows = [];
        foreach ($products as $product) {
            $remaining = (int) $product->quantity
                + (int) ($product->additions_sum ?? 0)
                - (int) ($product->outs_sum ?? 0);

            if (! $includeOutOfStock && $remaining <= 0) {
                continue;
            }

            $rows[] = [
                $product->name,
                $product->category,
                $product->model_type ?? '-',
                $remaining,
            ];
        }

        $filename = 'inventory-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel Arabic support
            fputcsv($out, ['الصنف', 'التصنيف', 'الموديل', 'الكمية المتبقية']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create()
    {
        return view('storage.create');
    }

    // Add a new item to storage
    public function store(Request $request)
    {
        $validated = $request->validate($this->itemRules(), [
            'added_at.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ في المستقبل!',
        ]);

        $this->inventory->createItem($validated, $request->user());

        return redirect()->route('storage.index')->with('success', 'تم إنشاء الصنف بنجاح!');
    }

    // 2. REMOVE ITEM
    public function storeOut(Request $request)
    {
        $validated = $request->validate([
            // REQUIRED FIELDS
            'product_in_id' => 'required|exists:product_ins,id',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date|before_or_equal:now',

            // OPTIONAL FIELDS
            'destination' => 'nullable',
            'note' => 'nullable',
        ], [
            'date.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ سحب في المستقبل!',
        ]);

        $this->inventory->createMovement(Out::class, $validated, $request->user());

        return back()->with('success', 'تم سحب العنصر من المخزن بنجاح!');
    }

    // ADD STOCK (new batch to an existing item)
    public function storeAddition(Request $request)
    {
        $validated = $request->validate([
            'product_in_id' => 'required|exists:product_ins,id',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date|before_or_equal:now',

            'source' => 'nullable',
            'note' => 'nullable',
        ], [
            'date.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ إضافة في المستقبل!',
        ]);

        $this->inventory->createMovement(Addition::class, $validated, $request->user());

        return back()->with('success', 'تمت إضافة الكمية للمخزون بنجاح!');
    }

    // --- UPDATE/DELETE ITEMS ---

    public function updateItem(Request $request, $id)
    {
        $item = ProductIn::findOrFail($id);
        $this->authorize('correct-inventory', $item);

        $rules = $this->itemRules($item->id);
        $rules['quantity'] = 'sometimes|integer|min:1|max:2147483647';
        $validated = $request->validate($rules);
        $saved = $this->inventory->updateItem((int) $id, $validated, $this->reason($request), $request->user());
        if ($saved->id !== $item->id) {
            return redirect()->route('storage.index', ['search' => $saved->name])->with('success', 'تم حفظ الصنف بالكمية المصححة مع الاحتفاظ بالسجل الأصلي.');
        }

        return back()->with('success', 'تم تعديل بيانات الصنف بنجاح!');
    }

    public function destroyItem(Request $request, $id)
    {
        $this->inventory->archive((int) $id, $this->reason($request), $request->user());

        return back()->with('success', 'تمت أرشفة العنصر مع الاحتفاظ بجميع سجلاته.');
    }

    // --- UPDATE/DELETE REMOVALS ---

    public function updateOut(Request $request, $id)
    {
        $out = Out::findOrFail($id);
        $this->authorize('correct-inventory', $out);

        $validated = $request->validate($this->movementRules('out'));
        $this->inventory->updateMovement(Out::class, (int) $id, $validated, $this->reason($request), $request->user());

        return back()->with('success', 'تم حفظ تعديلات عملية السحب.');
    }

    public function destroyOut(Request $request, $id)
    {
        $this->authorize('correct-inventory', Out::findOrFail($id));
        $this->inventory->cancel(Out::class, (int) $id, $this->reason($request), $request->user());

        return back()->with('success', 'تم إلغاء عملية السحب واسترجاع الكمية للمخزن.');
    }

    // --- UPDATE/DELETE ADDITIONS ---

    public function updateAddition(Request $request, $id)
    {
        $addition = Addition::findOrFail($id);
        $this->authorize('correct-inventory', $addition);

        $validated = $request->validate($this->movementRules('addition'));
        $this->inventory->updateMovement(Addition::class, (int) $id, $validated, $this->reason($request), $request->user());

        return back()->with('success', 'تم حفظ تعديلات عملية الإضافة.');
    }

    public function destroyAddition(Request $request, $id)
    {
        $this->authorize('correct-inventory', Addition::findOrFail($id));
        $this->inventory->cancel(Addition::class, (int) $id, $this->reason($request), $request->user());

        return back()->with('success', 'تم إلغاء الإضافة مع الاحتفاظ بسجلها.');
    }

    public function restoreItem(Request $request, $id)
    {
        $this->inventory->archive((int) $id, $this->reason($request), $request->user(), true);

        return back()->with('success', 'تمت إعادة تفعيل العنصر.');
    }

    public function cancelUnusedItem(Request $request, $id)
    {
        $item = ProductIn::findOrFail($id);
        $this->authorize('correct-inventory', $item);
        $this->inventory->cancelItem($item->id, $this->reason($request), $request->user());

        return back()->with('success', 'أُلغي الصنف وأزيل أثر كميته من المخزون مع الاحتفاظ بسجله.');
    }

    private function movementRules(string $kind): array
    {
        return [
            'product_in_id' => 'sometimes|required|integer|exists:product_ins,id',
            'quantity' => 'sometimes|required|integer|min:1|max:2147483647',
            'date' => 'required|date|before_or_equal:now',
            $kind === 'out' ? 'destination' : 'source' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ];
    }

    private function itemRules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255', 'category' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1|max:2147483647',
            'added_at' => 'required|date|before_or_equal:now',
            'reciever' => 'nullable|string|max:255',
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('product_ins', 'serial_number')->whereNull('cancelled_at')->ignore($ignoreId)],
            'manufacturer' => 'nullable|string|max:255', 'model_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    private function reason(Request $request): string
    {
        return $request->validate(['reason' => 'required|string|min:3|max:1000'])['reason'];
    }
}
