<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchasing\Http\PurchasingController;
use Modules\Purchasing\Http\PurchasingProductionController;

Route::prefix('purchasing')->middleware('module.enabled:purchasing')->group(function (): void {
    Route::get('production/requisitions', [PurchasingProductionController::class, 'requisitions'])->middleware('admin.permission:purchasing.view,global');
    Route::post('production/{operation}', [PurchasingProductionController::class, 'command'])->whereIn('operation', ['requisitions', 'orders', 'receipts', 'inspect', 'returns']);
    Route::get('commands/{key}', [PurchasingController::class, 'commandStatus'])->middleware('admin.permission:purchasing.view,global');
    Route::get('suppliers', [PurchasingController::class, 'suppliers'])->middleware('admin.permission:purchasing.view,global');
    Route::post('suppliers', [PurchasingController::class, 'createSupplier'])->middleware('admin.permission:purchasing.supplier.manage,global');
    Route::put('suppliers/{supplier}', [PurchasingController::class, 'updateSupplier'])->whereUuid('supplier')->middleware('admin.permission:purchasing.supplier.manage,global');
    Route::delete('suppliers/{supplier}', [PurchasingController::class, 'archiveSupplier'])->whereUuid('supplier')->middleware('admin.permission:purchasing.supplier.manage,global');
    Route::get('orders', [PurchasingController::class, 'orders'])->middleware('admin.permission:purchasing.view,global');
    Route::get('orders/{order}', [PurchasingController::class, 'show'])->middleware('admin.permission:purchasing.view,global');
    Route::post('orders', [PurchasingController::class, 'createOrder'])->middleware('admin.permission:purchasing.order.create,global');
    Route::post('orders/{order}/approve', [PurchasingController::class, 'approve'])->middleware('admin.permission:purchasing.order.approve,global');
    Route::post('orders/{order}/receive', [PurchasingController::class, 'receive'])->middleware('admin.permission:purchasing.receipt.manage,global');
    Route::post('orders/{order}/returns', [PurchasingController::class, 'returnGoods'])->middleware('admin.permission:purchasing.return.manage,global');
    Route::post('orders/{order}/payments', [PurchasingController::class, 'pay'])->middleware('admin.permission:purchasing.payment.manage,global');
    Route::post('orders/{order}/close', [PurchasingController::class, 'close'])->middleware('admin.permission:purchasing.order.approve,global');
});
