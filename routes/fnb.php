<?php

use Illuminate\Support\Facades\Route;
use Modules\FnbPos\Http\FnbApiController;
use Modules\FnbPos\Http\FnbApiErrors;
use Modules\FnbPos\Http\FnbCustomerController;
use Modules\FnbPos\Http\FnbReceiptController;
use Modules\FnbPos\Http\FnbReportExportController;
use Modules\FnbPos\Http\FnbSecurityController;

Route::prefix('fnb')->name('fnb.')->middleware([FnbApiErrors::class, 'module.enabled:fnb-pos'])->group(function (): void {
    Route::get('bootstrap', [FnbApiController::class, 'bootstrap'])->name('bootstrap');

    foreach ([
        ['outlets/{outlet}/dashboard', 'dashboard', 'fnb.dashboard.view'],
        ['outlets/{outlet}/pos', 'pos', 'fnb.order.view'],
        ['outlets/{outlet}/menu', 'menu', 'fnb.menu.view'],
        ['outlets/{outlet}/recipes', 'recipes', 'fnb.recipe.view'],
        ['outlets/{outlet}/kitchen/tickets', 'kitchen', 'fnb.kitchen.view'],
        ['outlets/{outlet}/shifts', 'shifts', 'fnb.shift.view'],
        ['outlets/{outlet}/business-days/current', 'business_day', 'fnb.shift.view'],
        ['outlets/{outlet}/settings', 'settings', 'fnb.settings.view'],
        ['outlets/{outlet}/reports/summary', 'report', 'fnb.outlet.view'],
        ['outlets/{outlet}/reports/exports', 'exports', 'fnb.report.export'],
        ['outlets/{outlet}/customers', 'customers', 'fnb.customer.lookup'],
    ] as [$path, $read, $permission]) {
        Route::get($path, [FnbApiController::class, 'read'])->defaults('fnb_read', $read)
            ->defaults('fnb_permission', $permission)->whereNumber('outlet')->name('read.'.$read);
    }

    foreach ([
        ['onboarding/presets/cafe', 'onboard', 'fnb.outlet.manage'],
        ['menu/items', 'item.save', 'fnb.menu.manage'],
        ['outlets/{outlet}/items/{resource}/availability', 'item.availability', 'fnb.menu.availability.update'],
        ['customers', 'customer.create', 'fnb.customer.create'],
        ['outlets/{outlet}/business-days/open', 'day.open', 'fnb.shift.open'],
        ['business-days/{resource}/close', 'day.close', 'fnb.shift.close'],
        ['outlets/{outlet}/shifts/open', 'shift.open', 'fnb.shift.open'],
        ['shifts/{resource}/close', 'shift.close', 'fnb.shift.close'],
        ['shifts/{resource}/reconcile', 'shift.reconcile', 'fnb.shift.close'],
        ['shifts/{resource}/cash-movements', 'cash.record', 'fnb.cash.adjust'],
        ['sessions', 'session.open', 'fnb.order.create'],
        ['sessions/{resource}/transfer-table', 'session.transfer', 'fnb.order.transfer'],
        ['sessions/{resource}/settle', 'session.settle', 'fnb.order.update'],
        ['sessions/{resource}/close', 'session.close', 'fnb.order.update'],
        ['sessions/{resource}/orders', 'order.create', 'fnb.order.create'],
        ['orders/{resource}/lines', 'line.add', 'fnb.order.update'],
        ['orders/{resource}/submit', 'order.submit', 'fnb.order.submit'],
        ['orders/{resource}/discount', 'order.discount', 'fnb.discount.apply'],
        ['order-lines/{resource}/void', 'line.void', 'fnb.order.void'],
        ['kitchen/lines/{resource}/transition', 'kitchen.transition', 'fnb.kitchen.update'],
        ['sessions/{resource}/checks', 'check.create', 'fnb.order.split'],
        ['checks/{resource}/finalize', 'check.finalize', 'fnb.payment.collect'],
        ['checks/{resource}/reopen', 'check.reopen', 'fnb.order.reopen'],
        ['checks/{resource}/void', 'check.void', 'fnb.order.void'],
        ['checks/{resource}/settlement-plan', 'check.plan', 'fnb.payment.collect'],
        ['checks/{resource}/payments', 'payment.collect', 'fnb.payment.collect'],
        ['payments/{resource}/cancel', 'payment.cancel', 'fnb.payment.collect'],
        ['payments/{resource}/refunds', 'payment.refund', 'fnb.payment.refund'],
        ['refunds/{resource}/cancel', 'refund.cancel', 'fnb.payment.refund'],
    ] as [$path, $command, $permission]) {
        Route::post($path, [FnbApiController::class, 'command'])->defaults('fnb_command', $command)
            ->defaults('fnb_permission', $permission)->whereNumber(['outlet', 'resource'])->name('command.'.$command);
    }
    Route::put('menu/items/{resource}', [FnbApiController::class, 'command'])
        ->defaults('fnb_command', 'item.save')->defaults('fnb_permission', 'fnb.menu.manage')->whereNumber('resource')->name('item.update');
    Route::put('order-lines/{resource}', [FnbApiController::class, 'command'])
        ->defaults('fnb_command', 'line.update')->defaults('fnb_permission', 'fnb.order.update')->whereNumber('resource')->name('line.update');
    Route::delete('order-lines/{resource}', [FnbApiController::class, 'command'])
        ->defaults('fnb_command', 'line.remove')->defaults('fnb_permission', 'fnb.order.update')->whereNumber('resource')->name('line.remove');

    Route::post('reauth/proofs', [FnbSecurityController::class, 'reauth'])->name('reauth');
    Route::get('customers/{resource}', [FnbCustomerController::class, 'show'])->defaults('fnb_permission', 'fnb.customer.view')->whereNumber('resource')->name('customer.show');
    Route::get('customers/{resource}/history', [FnbCustomerController::class, 'show'])->defaults('fnb_history', true)->defaults('fnb_permission', 'fnb.customer.view')->whereNumber('resource')->name('customer.history');
    Route::put('customers/{resource}', [FnbCustomerController::class, 'command'])->defaults('fnb_customer_action', 'update')
        ->defaults('fnb_permission', 'fnb.customer.update')->whereNumber('resource')->name('customer.update');
    Route::post('sessions/{resource}/customer', [FnbCustomerController::class, 'command'])->defaults('fnb_customer_action', 'attach')
        ->defaults('fnb_permission', 'fnb.customer.attach')->whereNumber('resource')->name('customer.attach');
    Route::post('recipes/publish', [FnbApiController::class, 'publishRecipe'])->defaults('fnb_permission', 'fnb.recipe.manage')->name('recipe.publish');
    Route::post('check-lines/{resource}/compensations', [FnbApiController::class, 'compensate'])
        ->defaults('fnb_permission', 'fnb.order.void')->whereNumber('resource')->name('fulfillment.compensate');
    Route::post('reports/exports', [FnbReportExportController::class, 'store'])->defaults('fnb_permission', 'fnb.report.export')->name('exports.create');
    Route::get('reports/exports/{resource}/download', [FnbReportExportController::class, 'download'])->whereNumber('resource')->name('exports.download');
    Route::get('print-jobs/{resource}', [FnbReceiptController::class, 'show'])->whereNumber('resource')->name('receipts.show');
    foreach ([['checks/{resource}/receipts', 'generate'], ['print-jobs/{resource}/request', 'request'], ['print-jobs/{resource}/confirm', 'confirm']] as [$path, $action]) {
        Route::post($path, [FnbReceiptController::class, 'command'])->defaults('fnb_receipt_action', $action)
            ->defaults('fnb_permission', 'fnb.payment.collect')->whereNumber('resource')->name('receipts.'.$action);
    }
    foreach ([
        ['outlets/{outlet}/terminals', 'terminal', 'fnb.terminal.manage'],
        ['outlets/{outlet}/areas', 'area', 'fnb.floor.manage'],
        ['outlets/{outlet}/tables', 'table', 'fnb.floor.manage'],
        ['outlets/{outlet}/stations', 'station', 'fnb.floor.manage'],
        ['outlets/{outlet}/payment-methods', 'payment_method', 'fnb.settings.manage'],
        ['menu/categories', 'category', 'fnb.menu.manage'],
        ['menu/modifier-groups', 'modifier_group', 'fnb.menu.manage'],
        ['menu/modifier-options', 'modifier_option', 'fnb.menu.manage'],
        ['ingredients', 'ingredient', 'fnb.recipe.manage'],
    ] as [$path, $kind, $permission]) {
        Route::post($path, [FnbApiController::class, 'configure'])->defaults('fnb_kind', $kind)
            ->defaults('fnb_permission', $permission)->whereNumber('outlet')->name('config.'.$kind.'.create');
        Route::put($path.'/{resource}', [FnbApiController::class, 'configure'])->defaults('fnb_kind', $kind)
            ->defaults('fnb_permission', $permission)->whereNumber(['outlet', 'resource'])->name('config.'.$kind.'.update');
    }
    Route::put('outlets/{outlet}', [FnbApiController::class, 'configure'])->defaults('fnb_kind', 'outlet')
        ->defaults('fnb_permission', 'fnb.outlet.manage')->whereNumber('outlet')->name('config.outlet.update');
    Route::post('approvals', [FnbSecurityController::class, 'requestApproval'])->name('approvals.request');
    Route::get('outlets/{outlet}/approvals', [FnbSecurityController::class, 'approvals'])->whereNumber('outlet')->name('approvals.index');
    Route::get('approvals/{resource}', [FnbSecurityController::class, 'approval'])->whereNumber('resource')->name('approvals.show');
    Route::post('approvals/{resource}/approve', [FnbSecurityController::class, 'approve'])->whereNumber('resource')->name('approvals.approve');
    Route::post('approvals/{resource}/reject', [FnbSecurityController::class, 'reject'])->whereNumber('resource')->name('approvals.reject');
    Route::post('approvals/{resource}/claim-token', [FnbSecurityController::class, 'claim'])->whereNumber('resource')->name('approvals.claim');
    Route::get('outlets/{outlet}/staff', [FnbSecurityController::class, 'staff'])->whereNumber('outlet')->name('staff.index');
    Route::post('outlets/{outlet}/staff-candidates/resolve', [FnbSecurityController::class, 'candidate'])->whereNumber('outlet')->name('staff.resolve');
    Route::post('outlets/{outlet}/staff-assignments', [FnbSecurityController::class, 'assign'])->whereNumber('outlet')->name('staff.assign');
    Route::delete('outlets/{outlet}/staff-assignments/{resource}', [FnbSecurityController::class, 'revoke'])->whereNumber(['outlet', 'resource'])->name('staff.revoke');
});
