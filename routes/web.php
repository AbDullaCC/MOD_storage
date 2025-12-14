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
Route::post('/storage', [StorageController::class, 'store'])->name('storage.store'); // Add Item
Route::post('/storage/out', [StorageController::class, 'storeOut'])->name('storage.out'); // Remove Item