<?php

namespace App\Http\Controllers;

use App\Models\ProductIn;
use App\Models\Out;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    // Show the dashboard / list
   public function index(Request $request)
    {
        // Change 'with('outs')' to this:
        $query = ProductIn::with(['outs' => function($q) {
            $q->orderBy('date', 'desc'); // Sort history by newest
        }]);

        // ... keep the search logic same as before ...
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
        }

        $products = $query->latest('added_at')->get();

        return view('storage.index', compact('products'));
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

    // --- REPORT METHOD ---
    public function report(Request $request)
    {
        // 1. Handle "All Time" vs Date Range
        if ($request->has('show_all')) {
            $start = \Carbon\Carbon::create(2000, 1, 1); // Way back in time
            $end = now()->endOfDay();
            $dateInputs = ['start' => '', 'end' => '']; // Clear inputs in UI
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
                    'note' => 'إضافة مخزنية',
                ];
            });
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
        $transactions = $ins->concat($outs)->sortByDesc('date');

        return view('storage.report', compact('transactions', 'dateInputs', 'typeFilter', 'querySearch'));
    }
}