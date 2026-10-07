<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage', [StorageController::class, 'index'])->name('storage.index');
Route::get('/storage/export', [StorageController::class, 'export'])->name('storage.export');
Route::post('/storage', [StorageController::class, 'store'])->name('storage.store'); // Add Item
Route::post('/storage/out', [StorageController::class, 'storeOut'])->name('storage.out'); // Remove Item
Route::post('/storage/addition', [StorageController::class, 'storeAddition'])->name('storage.addition'); // Add batch to existing item
Route::get('/storage/report', [StorageController::class, 'report'])->name('storage.report');

// Edit/Delete ITEMS
Route::put('/storage/item/{id}', [StorageController::class, 'updateItem'])->name('storage.updateItem');
Route::delete('/storage/item/{id}', [StorageController::class, 'destroyItem'])->name('storage.destroyItem');

// Edit/Delete REMOVALS (Outs)
Route::put('/storage/out/{id}', [StorageController::class, 'updateOut'])->name('storage.updateOut');
Route::delete('/storage/out/{id}', [StorageController::class, 'destroyOut'])->name('storage.destroyOut');

// Edit/Delete ADDITIONS (Restocks)
Route::put('/storage/addition/{id}', [StorageController::class, 'updateAddition'])->name('storage.updateAddition');
Route::delete('/storage/addition/{id}', [StorageController::class, 'destroyAddition'])->name('storage.destroyAddition');