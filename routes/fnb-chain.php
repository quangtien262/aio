<?php

use Illuminate\Support\Facades\Route;
use Modules\FnbPos\Http\FnbApiErrors;
use Modules\FnbPos\Http\FnbChainController;

Route::prefix('fnb/chain')->middleware([FnbApiErrors::class, 'module.enabled:fnb-pos'])->group(function (): void {
    Route::get('bootstrap', [FnbChainController::class, 'bootstrap']);
    Route::get('audit', [FnbChainController::class, 'audit']);
    Route::post('menus', [FnbChainController::class, 'menuCommand'])->defaults('fnb_chain_action', 'chain.menu.create');
    Route::get('menu', [FnbChainController::class, 'menu']);
    Route::post('menu/publications', [FnbChainController::class, 'menuCommand'])->defaults('fnb_chain_action', 'chain.menu.publish');
    Route::post('menu/publications/{resource}/rollback', [FnbChainController::class, 'menuCommand'])->whereNumber('resource')->defaults('fnb_chain_action', 'chain.menu.rollback');
    Route::post('price-books', [FnbChainController::class, 'menuCommand'])->defaults('fnb_chain_action', 'chain.pricebook.save');
    Route::post('staff', [FnbChainController::class, 'assignHq']);
    Route::delete('staff/{resource}', [FnbChainController::class, 'revokeHq'])->whereNumber('resource');
    foreach (['brands' => 'brand', 'regions' => 'region', 'outlets' => 'outlet'] as $path => $kind) {
        Route::post($path, [FnbChainController::class, 'save'])->defaults('fnb_chain_kind', $kind);
        Route::put($path.'/{resource}', [FnbChainController::class, 'save'])->whereNumber('resource')->defaults('fnb_chain_kind', $kind);
    }
});
