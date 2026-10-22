<?php

use Illuminate\Support\Facades\Route;
use Modules\RetailPos\Http\RetailController;
use Modules\RetailPos\Http\RetailIntegrationController;

Route::prefix('retail')->name('retail.')->middleware('module.enabled:retail-pos')->group(function (): void {
    Route::get('customers/{resource}/history', [RetailController::class, 'customerHistory'])->middleware('admin.permission:retail.customer.view');
    Route::get('integrations', [RetailIntegrationController::class, 'show'])->middleware('admin.permission:retail.integration.manage');
    Route::put('integrations/mapping', [RetailIntegrationController::class, 'mapping'])->middleware('admin.permission:retail.integration.manage');
    Route::post('integrations/reconcile', [RetailIntegrationController::class, 'reconcile'])->middleware('admin.permission:retail.integration.manage');
    foreach (['bootstrap' => 'view', 'branches' => 'view', 'settings' => 'settings.manage', 'products' => 'product.view', 'units' => 'product.manage', 'dashboard' => 'report.view', 'reports' => 'report.view', 'sales' => 'sale.view', 'shifts' => 'shift.manage', 'returns' => 'return.manage', 'customer-context' => 'customer.view', 'customers' => 'customer.view', 'inventory' => 'inventory.view', 'terminals' => 'view', 'transfers' => 'transfer.manage', 'stocktakes' => 'stocktake.manage', 'commands/{resource}' => 'view'] as $path => $permission) {
        Route::get($path, [RetailController::class, 'read'])->defaults('retail_read', str_starts_with($path, 'commands/') ? 'command' : $path)->middleware('admin.permission:retail.'.$permission);
    }
    foreach ([
        ['post', 'units', 'unit.create', 'product.manage'], ['put', 'units/{resource}', 'unit.update', 'product.manage'], ['delete', 'units/{resource}', 'unit.archive', 'product.manage'],
        ['post', 'setup', 'setup', 'setup.manage'], ['post', 'branches', 'branch.create', 'branch.manage'], ['post', 'terminals', 'terminal.create', 'branch.manage'], ['post', 'products', 'product.create', 'product.manage'], ['post', 'assortments', 'assortment.save', 'product.manage'], ['put', 'products/{resource}', 'assortment.save', 'product.manage'],
        ['post', 'inventory/receipts', 'receipt.create', 'inventory.view'], ['post', 'shifts', 'shift.open', 'shift.manage'], ['post', 'shifts/{resource}/close', 'shift.close', 'shift.manage'], ['post', 'shifts/{resource}/reconcile', 'shift.reconcile', 'shift.manage'],
        ['post', 'sales', 'sale.create', 'sale.create'], ['post', 'sales/{resource}/complete', 'sale.complete', 'payment.collect'], ['post', 'sales/{resource}/cancel', 'sale.cancel', 'sale.create'], ['post', 'sales/{resource}/returns', 'sale.return', 'return.manage'], ['post', 'returns/{resource}/refund', 'return.refund', 'return.manage'],
        ['post', 'customers', 'customer.create', 'customer.manage'], ['put', 'customers/{resource}', 'customer.update', 'customer.manage'], ['post', 'memberships', 'membership.save', 'membership.manage'],
        ['post', 'transfers', 'transfer.create', 'transfer.manage'], ['post', 'transfers/{resource}/dispatch', 'transfer.dispatch', 'transfer.manage'], ['post', 'transfers/{resource}/receive', 'transfer.receive', 'transfer.manage'], ['post', 'stocktakes', 'stocktake.create', 'stocktake.manage'], ['post', 'stocktakes/{resource}/post', 'stocktake.post', 'stocktake.manage'],
    ] as [$method,$path,$operation,$permission]) {
        Route::$method($path, [RetailController::class, 'mutate'])->defaults('retail_command', $operation)->middleware('admin.permission:retail.'.$permission);
    }
});
