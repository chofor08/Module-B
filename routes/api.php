<?php

use App\Http\Controllers\Api\ItemsController;
use App\Http\Controllers\Api\LedgerEntriesController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\APi\OrderManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/auth.php';

// View items route
Route::get('/items', [ItemsController::class, 'index']);

// Order management routes
Route::middleware('auth:sanctum')->group(function() {
    Route::post('/checkout', [OrderManagementController::class, 'checkout'])->middleware('idempotency');
    Route::post('/refund', [OrderManagementController::class, 'refund']);
    Route::get('/order_items', [OrderManagementController::class, 'index']);
    Route::get('/orders', [OrderManagementController::class, 'reciept']);
});

    // After checkout routes
    Route::get('/success', [OrderManagementController::class, 'success'])->name('checkout.success');
    Route::post('/webhook', [OrderManagementController::class, 'webhook'])->name('checkout.webhook');
    Route::get('/cancel', [OrderManagementController::class, 'cancel'])->name('checkout.cancel');

    // Ledger entry routes
    Route::get('/ledger', [LedgerEntriesController::class, 'ledger']);
