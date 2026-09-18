<?php

use App\Http\Controllers\Api\V1\Cms\ContentCategoryController;
use App\Http\Controllers\Api\V1\Cms\ContentMediaController;
use App\Http\Controllers\Api\V1\Cms\ContentPostController;
use App\Http\Controllers\Api\V1\Cms\ContentPostTranslationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/cms')
    ->middleware(['content.api', 'module.enabled:cms', 'throttle:content-publishing'])
    ->name('api.v1.cms.')
    ->group(function (): void {
        Route::get('/categories', ContentCategoryController::class)
            ->middleware('content.ability:posts.read')
            ->name('categories.index');
        Route::post('/media', [ContentMediaController::class, 'store'])
            ->middleware('content.ability:media.write')
            ->name('media.store');
        Route::post('/posts/upsert', [ContentPostController::class, 'upsert'])
            ->middleware('content.ability:posts.write')
            ->name('posts.upsert');
        Route::post('/posts/{externalId}/translations/{locale}/upsert', [ContentPostTranslationController::class, 'upsert'])
            ->where('externalId', '[A-Za-z0-9._:-]+')
            ->where('locale', '[A-Za-z0-9_-]+')
            ->middleware('content.ability:translations.write')
            ->name('posts.translations.upsert');
        Route::get('/posts/{externalId}/translations/{locale}', [ContentPostTranslationController::class, 'show'])
            ->where('externalId', '[A-Za-z0-9._:-]+')
            ->where('locale', '[A-Za-z0-9_-]+')
            ->middleware('content.ability:translations.read')
            ->name('posts.translations.show');
        Route::get('/posts/{externalId}', [ContentPostController::class, 'show'])
            ->where('externalId', '[A-Za-z0-9._:-]+')
            ->middleware('content.ability:posts.read')
            ->name('posts.show');
    });
