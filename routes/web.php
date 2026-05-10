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
use App\Support\FrontendLocalization;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
	$locale = FrontendLocalization::resolveLocale(session('frontend_locale'));

	return to_route('site.home', FrontendLocalization::routeParameterDefaults($locale));
})->name('site.entry');

require __DIR__.'/admin.php';

Route::prefix('{locale}')
	->middleware('frontend.locale')
	->whereIn('locale', FrontendLocalization::supportedLocales())
	->group(function (): void {
		Route::get('/', LandingController::class)->name('site.home');

		Route::middleware('guest:customer')->group(function (): void {
			Route::get('/{loginSegment}', [CustomerAuthenticatedSessionController::class, 'create'])
				->whereIn('loginSegment', FrontendLocalization::segmentValues('login'))
				->name('customer.auth.login');
			Route::post('/{loginSegment}', [CustomerAuthenticatedSessionController::class, 'store'])
				->whereIn('loginSegment', FrontendLocalization::segmentValues('login'))
				->name('customer.auth.store');
			Route::get('/{registerSegment}', [RegisteredUserController::class, 'create'])
				->whereIn('registerSegment', FrontendLocalization::segmentValues('register'))
				->name('customer.auth.register');
			Route::post('/{registerSegment}', [RegisteredUserController::class, 'store'])
				->whereIn('registerSegment', FrontendLocalization::segmentValues('register'))
				->name('customer.auth.register.store');
		});

		Route::middleware('auth:customer')->group(function (): void {
			Route::prefix('/{accountSegment}/api')
				->whereIn('accountSegment', FrontendLocalization::segmentValues('account'))
				->name('customer.api.')
				->group(function (): void {
					Route::get('/overview', AccountOverviewController::class)->name('overview');
					Route::put('/profile', ProfileUpdateController::class)->name('profile.update');
					Route::delete('/favorites/{favorite}', [FavoriteManagementController::class, 'destroy'])->name('favorites.destroy');
				});

			Route::post('/{favoriteSegment}/{product:slug}', CustomerFavoriteController::class)
				->whereIn('favoriteSegment', FrontendLocalization::segmentValues('favorite'))
				->name('site.favorite.toggle');
			Route::get('/{accountSegment}/{any?}', CustomerAccountController::class)
				->whereIn('accountSegment', FrontendLocalization::segmentValues('account'))
				->where('any', '.*')
				->name('customer.account');
			Route::post('/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('customer.auth.logout');
		});

		Route::post('/{newsletterSegment}/{subscribeSegment}', NewsletterSubscriptionController::class)
			->whereIn('newsletterSegment', FrontendLocalization::segmentValues('newsletter'))
			->whereIn('subscribeSegment', FrontendLocalization::segmentValues('subscribe'))
			->name('site.newsletter.subscribe');

		Route::post('/theme-preset/{preset}', [CmsSiteController::class, 'switchThemePreset'])
			->name('site.theme.preset.switch');

		Route::middleware('auth:admin')->group(function (): void {
			Route::get('/{previewSegment}/{pagesSegment}/{page}', [CmsSiteController::class, 'previewPage'])
				->whereIn('previewSegment', FrontendLocalization::segmentValues('preview'))
				->whereIn('pagesSegment', FrontendLocalization::segmentValues('pages'))
				->name('site.preview.pages');
			Route::get('/{previewSegment}/{postsSegment}/{post}', [CmsSiteController::class, 'previewPost'])
				->whereIn('previewSegment', FrontendLocalization::segmentValues('preview'))
				->whereIn('postsSegment', FrontendLocalization::segmentValues('posts'))
				->name('site.preview.posts');
			Route::get('/{previewSegment}/{productsSegment}/{product}', [CmsSiteController::class, 'previewProduct'])
				->whereIn('previewSegment', FrontendLocalization::segmentValues('preview'))
				->whereIn('productsSegment', FrontendLocalization::segmentValues('products'))
				->name('site.preview.products');
		});

		Route::get('/{blogSegment}', [CmsSiteController::class, 'postsIndex'])
			->whereIn('blogSegment', FrontendLocalization::segmentValues('blog'))
			->name('site.blog.index');
		Route::get('/{blogSegment}/{slug}', [CmsSiteController::class, 'post'])
			->whereIn('blogSegment', FrontendLocalization::segmentValues('blog'))
			->name('site.blog.show');
		Route::post('/{contactSegment}', [CmsSiteController::class, 'submitContact'])
			->whereIn('contactSegment', FrontendLocalization::segmentValues('contact'))
			->name('site.contact.submit');
		Route::get('/{cartSegment}', [CmsSiteController::class, 'cart'])
			->whereIn('cartSegment', FrontendLocalization::segmentValues('cart'))
			->name('site.cart.index');
		Route::post('/{cartSegment}/{slug}', [CmsSiteController::class, 'addToCart'])
			->whereIn('cartSegment', FrontendLocalization::segmentValues('cart'))
			->name('site.cart.add');
		Route::post('/{cartSegment}/{slug}/{buyNowSegment}', [CmsSiteController::class, 'buyNow'])
			->whereIn('cartSegment', FrontendLocalization::segmentValues('cart'))
			->whereIn('buyNowSegment', FrontendLocalization::segmentValues('buy_now'))
			->name('site.cart.buy_now');
		Route::post('/{cartSegment}/{cartUpdateSegment}/{productId}', [CmsSiteController::class, 'updateCartItem'])
			->whereIn('cartSegment', FrontendLocalization::segmentValues('cart'))
			->whereIn('cartUpdateSegment', FrontendLocalization::segmentValues('cart_update'))
			->name('site.cart.update');
		Route::post('/{cartSegment}/{cartRemoveSegment}/{productId}', [CmsSiteController::class, 'removeCartItem'])
			->whereIn('cartSegment', FrontendLocalization::segmentValues('cart'))
			->whereIn('cartRemoveSegment', FrontendLocalization::segmentValues('cart_remove'))
			->name('site.cart.remove');
		Route::get('/{checkoutSegment}', [CmsSiteController::class, 'checkout'])
			->whereIn('checkoutSegment', FrontendLocalization::segmentValues('checkout'))
			->name('site.checkout.index');
		Route::post('/{checkoutSegment}', [CmsSiteController::class, 'placeOrder'])
			->whereIn('checkoutSegment', FrontendLocalization::segmentValues('checkout'))
			->name('site.checkout.store');
		Route::get('/{checkoutSegment}/{checkoutSuccessSegment}/{order}', [CmsSiteController::class, 'checkoutSuccess'])
			->whereIn('checkoutSegment', FrontendLocalization::segmentValues('checkout'))
			->whereIn('checkoutSuccessSegment', FrontendLocalization::segmentValues('checkout_success'))
			->name('site.checkout.success');
		Route::get('/{searchSegment}/{suggestionsSegment}', [CmsSiteController::class, 'searchProductSuggestions'])
			->whereIn('searchSegment', FrontendLocalization::segmentValues('search'))
			->whereIn('suggestionsSegment', FrontendLocalization::segmentValues('suggestions'))
			->name('site.catalog.search.suggestions');
		Route::get('/{searchSegment}', [CmsSiteController::class, 'searchProducts'])
			->whereIn('searchSegment', FrontendLocalization::segmentValues('search'))
			->name('site.catalog.search');
		Route::get('/{categorySegment}/{slug}', [CmsSiteController::class, 'category'])
			->whereIn('categorySegment', FrontendLocalization::segmentValues('category'))
			->name('site.catalog.category');
		Route::get('/{productSegment}/{slug}', [CmsSiteController::class, 'product'])
			->whereIn('productSegment', FrontendLocalization::segmentValues('product'))
			->name('site.catalog.product');
		Route::get('/{slug}', [CmsSiteController::class, 'page'])->where('slug', '[^/]+');
	});
