<?php

use App\Http\Controllers\Api\ItemsController;
use App\Http\Controllers\APi\OrderManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/auth.php';

// Items Routes
Route::get('/items', [ItemsController::class, 'index']);
Route::post('/checkout', [ItemsController::class, 'checkout']);
Route::get('/success', [ItemsController::class, 'success'])->name('checkout.success');
Route::get('/cancel', [ItemsController::class, 'cancel'])->name('checkout.cancel');

// OrderItems Routes
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/order', [OrderManagementController::class, 'index']);
    Route::post('/order', [OrderManagementController::class, 'store']);
});

