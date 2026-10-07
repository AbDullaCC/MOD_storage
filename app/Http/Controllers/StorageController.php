<?php

namespace App\Http\Controllers;

use App\Models\ProductIn;
use App\Models\Addition;
use App\Models\Out;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StorageController extends Controller
{
    // Show the dashboard / list
   public function index(Request $request)
{
    $query = ProductIn::with([
        'outs' => function($q) {
            $q->orderBy('date', 'desc');
        },
        'additions' => function($q) {
            $q->orderBy('date', 'desc');
        },
    ]);

    if ($request->has('search')) {
        $search = $request->get('search');
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('serial_number', 'like', "%{$search}%")
              ->orWhere('category', 'like', "%{$search}%");
    }

    // CHANGED: get() -> paginate(20)
    // withQueryString() ensures filters stay when you click "Page 2"
    $products = $query->latest('added_at')->paginate(20)->withQueryString();

    return view('storage.index', compact('products'));
}

    // Export inventory to CSV (Excel-compatible)
    public function export(Request $request)
    {
        $query = ProductIn::withSum('additions as additions_sum', 'quantity')
            ->withSum('outs as outs_sum', 'quantity');

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

            if (!$includeOutOfStock && $remaining <= 0) {
                continue;
            }

            $rows[] = [
                $product->name,
                $product->category,
                $product->model_type ?? '-',
                $remaining,
            ];
        }

        $filename = 'inventory-' . now()->format('Y-m-d') . '.csv';

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

    // Add a new item to storage
    public function store(Request $request)
    {
        $validated = $request->validate([
            // REQUIRED FIELDS
            'name' => 'required',
            'category' => 'required',
            'quantity' => 'required|integer|min:1',
            'added_at' => 'required|date|before_or_equal:now',
            
            // OPTIONAL FIELDS (Nullable)
            'reciever' => 'nullable', 
            'serial_number' => 'nullable|unique:product_ins,serial_number',
            'manufacturer' => 'nullable',
            'model_type' => 'nullable',
            'description' => 'nullable',
        ], [
            'added_at.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ في المستقبل!'
        ]);

        ProductIn::create($validated);

        return back()->with('success', 'تمت إضافة العنصر بنجاح!');
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
            'date.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ سحب في المستقبل!'
        ]);

        $product = ProductIn::find($request->product_in_id);

        if ($validated['quantity'] > $product->current_stock) {
            return back()->with('error', 'خطأ: الكمية المطلوبة غير متوفرة في المخزون!');
        }

        Out::create($validated);

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
            'date.before_or_equal' => 'خطأ: لا يمكن اختيار تاريخ إضافة في المستقبل!'
        ]);

        Addition::create($validated);

        return back()->with('success', 'تمت إضافة الكمية للمخزون بنجاح!');
    }

    // --- UPDATE/DELETE ITEMS ---

    public function updateItem(Request $request, $id)
    {
        $item = ProductIn::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required',
            'category' => 'required',
            'added_at' => 'required|date|before_or_equal:now',
            'reciever' => 'nullable',
            'manufacturer' => 'nullable',
            'model_type' => 'nullable',
            'serial_number' => 'nullable|unique:product_ins,serial_number,'.$item->id, // Ignore self for unique check
            'description' => 'nullable',
        ]);

        // Note: We deliberately do NOT update 'quantity' here.
        $item->update($validated);

        return back()->with('success', 'تم تعديل بيانات العنصر بنجاح!');
    }

    public function destroyItem($id)
    {
        $item = ProductIn::findOrFail($id);
        $item->delete(); // This deletes the item AND all its removal history
        return back()->with('success', 'تم حذف العنصر وجميع سجلاته نهائياً.');
    }

    // --- UPDATE/DELETE REMOVALS ---

    public function updateOut(Request $request, $id)
    {
        $out = Out::findOrFail($id);

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:now',
            'destination' => 'nullable',
            'note' => 'nullable',
        ]);

        // Quantity is excluded from update
        $out->update($validated);

        return back()->with('success', 'تم تعديل بيانات السحب بنجاح.');
    }

    public function destroyOut($id)
    {
        $out = Out::findOrFail($id);
        $out->delete(); // Stock is automatically recalculated
        return back()->with('success', 'تم إلغاء عملية السحب واسترجاع الكمية للمخزن.');
    }

    // --- UPDATE/DELETE ADDITIONS ---

    public function updateAddition(Request $request, $id)
    {
        $addition = Addition::findOrFail($id);

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:now',
            'source' => 'nullable',
            'note' => 'nullable',
        ]);

        // Quantity is excluded from update
        $addition->update($validated);

        return back()->with('success', 'تم تعديل بيانات الإضافة بنجاح.');
    }

    public function destroyAddition($id)
    {
        $addition = Addition::findOrFail($id);

        $product = $addition->productIn;
        if ($product) {
            $stockWithoutThis = $product->quantity
                + (int) $product->additions()->where('id', '!=', $addition->id)->sum('quantity')
                - (int) $product->outs()->sum('quantity');

            if ($stockWithoutThis < 0) {
                return back()->with('error', 'خطأ: لا يمكن حذف هذه الإضافة لأن جزءاً منها تم سحبه من المخزون!');
            }
        }

        $addition->delete(); // Stock is automatically recalculated
        return back()->with('success', 'تم حذف عملية الإضافة بنجاح.');
    }

    // --- REPORT METHOD ---
    public function report(Request $request)
    {
        // 1. Handle "All Time" vs Date Range
        if ($request->has('show_all')) {
            $start = \Carbon\Carbon::create(2000, 1, 1);
            $end = \Carbon\Carbon::create(2030, 12, 31); // Future date to catch everything
            
            // FIX: We populate the inputs so they persist during search/filter
            $dateInputs = ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')]; 
        } else {
            $start = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfMonth();
            $end = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
            $dateInputs = ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        }

        $querySearch = $request->search;
        $typeFilter = $request->type ?? 'all'; // 'all', 'in', 'out'

        // 2. Fetch INs (Additions)
        $ins = collect([]);
        if ($typeFilter == 'all' || $typeFilter == 'in') {
            $insQuery = ProductIn::whereBetween('added_at', [$start, $end]);
            
            // Search Logic for Inputs
            if ($querySearch) {
                $insQuery->where(function($q) use ($querySearch) {
                    $q->where('name', 'like', "%$querySearch%")
                      ->orWhere('reciever', 'like', "%$querySearch%")
                      ->orWhere('serial_number', 'like', "%$querySearch%");
                });
            }

            $ins = $insQuery->get()->map(function ($item) {
                return [
                    'type' => 'in',
                    'date' => $item->added_at, // Assumes you added 'datetime' cast to Model
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'sn' => $item->serial_number,
                    'party' => $item->reciever, 
                    'note' => 'إضافة مخزنية (رصيد افتتاحي)',
                ];
            });

            // Restock additions (new batches on existing items)
            $restocksQuery = Addition::with('productIn')->whereBetween('date', [$start, $end]);

            if ($querySearch) {
                $restocksQuery->where(function($q) use ($querySearch) {
                    $q->where('source', 'like', "%$querySearch%")
                      ->orWhere('note', 'like', "%$querySearch%")
                      ->orWhereHas('productIn', function($subQ) use ($querySearch) {
                          $subQ->where('name', 'like', "%$querySearch%")
                               ->orWhere('serial_number', 'like', "%$querySearch%");
                      });
                });
            }

            $restocks = $restocksQuery->get()->map(function ($item) {
                return [
                    'type' => 'in',
                    'date' => $item->date,
                    'name' => $item->productIn->name ?? 'عنصر محذوف',
                    'quantity' => $item->quantity,
                    'sn' => $item->productIn->serial_number ?? '-',
                    'party' => $item->source,
                    'note' => $item->note ?? 'إضافة كمية (دفعة جديدة)',
                ];
            });

            $ins = $ins->concat($restocks);
        }

        // 3. Fetch OUTs (Removals)
        $outs = collect([]);
        if ($typeFilter == 'all' || $typeFilter == 'out') {
            $outsQuery = Out::with('productIn')->whereBetween('date', [$start, $end]);
            
            // Search Logic for Outputs
            if ($querySearch) {
                $outsQuery->where(function($q) use ($querySearch) {
                    $q->where('destination', 'like', "%$querySearch%")
                      ->orWhere('note', 'like', "%$querySearch%")
                      ->orWhereHas('productIn', function($subQ) use ($querySearch) {
                          $subQ->where('name', 'like', "%$querySearch%")
                               ->orWhere('serial_number', 'like', "%$querySearch%");
                      });
                });
            }

            $outs = $outsQuery->get()->map(function ($item) {
                return [
                    'type' => 'out',
                    'date' => $item->date, // Assumes you added 'datetime' cast to Model
                    'name' => $item->productIn->name ?? 'عنصر محذوف',
                    'quantity' => $item->quantity,
                    'sn' => $item->productIn->serial_number ?? '-',
                    'party' => $item->destination,
                    'note' => $item->note ?? 'سحب مخزني',
                ];
            });
        }

        // 4. Merge and Sort
        $allTransactions = $ins->concat($outs)->sortByDesc('date');

        // 2. Manual Pagination Logic
    $perPage = 20; // Items per page
    $currentPage = LengthAwarePaginator::resolveCurrentPage();
    $currentItems = $allTransactions->slice(($currentPage - 1) * $perPage, $perPage)->all();

    $transactions = new LengthAwarePaginator(
        $currentItems,
        $allTransactions->count(),
        $perPage,
        $currentPage,
        ['path' => $request->url(), 'query' => $request->query()] // Keeps the filters in the URL
    );

        return view('storage.report', compact('transactions', 'dateInputs', 'typeFilter', 'querySearch'));
    }
}