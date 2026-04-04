<?php

use App\Http\Controllers\Customer\Api\AccountOverviewController;
use App\Http\Controllers\Customer\Api\FavoriteManagementController;
use App\Http\Controllers\Customer\Api\ProfileUpdateController;
use App\Http\Controllers\Customer\AuthenticatedSessionController as CustomerAuthenticatedSessionController;
use App\Http\Controllers\Customer\CustomerAccountController;
use App\Http\Controllers\Customer\CustomerFavoriteController;
use App\Http\Controllers\Customer\NewsletterSubscriptionController;
use App\Http\Controllers\Customer\RegisteredUserController;
use App\Http\Controllers\Site\CmsSiteController;
use App\Http\Controllers\Site\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('site.home');

Route::middleware('guest:customer')->group(function (): void {
	Route::get('/login', [CustomerAuthenticatedSessionController::class, 'create'])->name('customer.auth.login');
	Route::post('/login', [CustomerAuthenticatedSessionController::class, 'store'])->name('customer.auth.store');
	Route::get('/register', [RegisteredUserController::class, 'create'])->name('customer.auth.register');
	Route::post('/register', [RegisteredUserController::class, 'store'])->name('customer.auth.register.store');
});

Route::middleware('auth:customer')->group(function (): void {
	Route::prefix('/account/api')->name('customer.api.')->group(function (): void {
		Route::get('/overview', AccountOverviewController::class)->name('overview');
		Route::put('/profile', ProfileUpdateController::class)->name('profile.update');
		Route::delete('/favorites/{favorite}', [FavoriteManagementController::class, 'destroy'])->name('favorites.destroy');
	});
	Route::post('/yeu-thich/{product:slug}', CustomerFavoriteController::class)->name('site.favorite.toggle');
	Route::get('/account/{any?}', CustomerAccountController::class)->where('any', '.*')->name('customer.account');
	Route::post('/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('customer.auth.logout');
});

Route::post('/newsletter/subscribe', NewsletterSubscriptionController::class)->name('site.newsletter.subscribe');

require __DIR__.'/admin.php';

Route::middleware('auth:admin')->group(function (): void {
    Route::get('/preview/pages/{page}', [CmsSiteController::class, 'previewPage'])->name('site.preview.pages');
    Route::get('/preview/posts/{post}', [CmsSiteController::class, 'previewPost'])->name('site.preview.posts');
});

Route::get('/tin-tuc', [CmsSiteController::class, 'postsIndex'])->name('site.blog.index');
Route::get('/tin-tuc/{slug}', [CmsSiteController::class, 'post'])->name('site.blog.show');
Route::get('/blog', [CmsSiteController::class, 'postsIndex']);
Route::get('/blog/{slug}', [CmsSiteController::class, 'post']);
Route::post('/lien-he', [CmsSiteController::class, 'submitContact'])->name('site.contact.submit');
Route::get('/gio-hang', [CmsSiteController::class, 'cart'])->name('site.cart.index');
Route::post('/gio-hang/{slug}', [CmsSiteController::class, 'addToCart'])->name('site.cart.add');
Route::post('/gio-hang/{slug}/mua-ngay', [CmsSiteController::class, 'buyNow'])->name('site.cart.buy_now');
Route::post('/gio-hang/cap-nhat/{productId}', [CmsSiteController::class, 'updateCartItem'])->name('site.cart.update');
Route::post('/gio-hang/xoa/{productId}', [CmsSiteController::class, 'removeCartItem'])->name('site.cart.remove');
Route::get('/thanh-toan', [CmsSiteController::class, 'checkout'])->name('site.checkout.index');
Route::post('/thanh-toan', [CmsSiteController::class, 'placeOrder'])->name('site.checkout.store');
Route::get('/thanh-toan/thanh-cong/{order}', [CmsSiteController::class, 'checkoutSuccess'])->name('site.checkout.success');
Route::get('/tim-kiem/goi-y', [CmsSiteController::class, 'searchProductSuggestions'])->name('site.catalog.search.suggestions');
Route::get('/tim-kiem', [CmsSiteController::class, 'searchProducts'])->name('site.catalog.search');
Route::get('/danh-muc/{slug}', [CmsSiteController::class, 'category'])->name('site.catalog.category');
Route::get('/san-pham/{slug}', [CmsSiteController::class, 'product'])->name('site.catalog.product');
Route::fallback([CmsSiteController::class, 'page']);
