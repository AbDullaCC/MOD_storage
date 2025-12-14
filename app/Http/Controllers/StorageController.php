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
        $query = ProductIn::with('outs');

        // Search Logic
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
        }

        $products = $query->latest()->get();

        return view('storage.index', compact('products'));
    }

    // Add a new item to storage
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'category' => 'required',
            'quantity' => 'required|integer|min:1',
            'reciever' => 'required',
            'added_at' => 'required|date',
            'serial_number' => 'nullable|unique:product_ins,serial_number',
        ]);

        ProductIn::create($validated + [
            'manufacturer' => $request->manufacturer,
            'model_type' => $request->model_type,
            'description' => $request->description,
        ]);

        return back()->with('success', 'تمت إضافة العنصر بنجاح!');
    }

    // Remove item from storage
    public function storeOut(Request $request)
    {
        $validated = $request->validate([
            'product_in_id' => 'required|exists:product_ins,id',
            'quantity' => 'required|integer|min:1',
            'destination' => 'required',
            'date' => 'required|date',
        ]);

        $product = ProductIn::find($request->product_in_id);

        // Validation: Don't remove more than we have!
        if ($validated['quantity'] > $product->current_stock) {
            return back()->with('error', 'خطأ: الكمية المطلوبة غير متوفرة في المخزون!');
        }

        Out::create($validated + ['note' => $request->note]);

        return back()->with('success', 'تم سحب العنصر من المخزن بنجاح!');
    }
}