<?php

use App\Http\Controllers\Customer\Api\AccountOverviewController;
use App\Http\Controllers\Customer\Api\AddressManagementController;
use App\Http\Controllers\Customer\Api\FavoriteManagementController;
use App\Http\Controllers\Customer\Api\ProfileUpdateController;
use App\Http\Controllers\Customer\Api\PasswordUpdateController;
use App\Http\Controllers\Customer\Api\ServiceInterestController;
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
	$locale = FrontendLocalization::defaultLocale();

	return redirect('/'.$locale);
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
					Route::put('/password', PasswordUpdateController::class)->name('password.update');
					Route::post('/addresses', [AddressManagementController::class, 'store'])->name('addresses.store');
					Route::put('/addresses/{address}', [AddressManagementController::class, 'update'])->name('addresses.update');
					Route::put('/addresses/{address}/default', [AddressManagementController::class, 'markDefault'])->name('addresses.default');
					Route::delete('/addresses/{address}', [AddressManagementController::class, 'destroy'])->name('addresses.destroy');
					Route::delete('/favorites/{favorite}', [FavoriteManagementController::class, 'destroy'])->name('favorites.destroy');
					Route::post('/service-interests', [ServiceInterestController::class, 'store'])->name('service_interests.store');
					Route::delete('/service-interests/{interest}', [ServiceInterestController::class, 'destroy'])->name('service_interests.destroy');
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
		Route::get('/land/{slug}', [CmsSiteController::class, 'landing'])
			->name('site.landing.show');

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

		Route::get('/c', [CmsSiteController::class, 'postsIndex'])
			->name('site.blog.index');
		Route::get('/c/{slug}', [CmsSiteController::class, 'postsIndex'])
			->name('site.blog.category');
		Route::get('/n/{slug}', [CmsSiteController::class, 'post'])
			->name('site.blog.show');
		Route::get('/s', [CmsSiteController::class, 'servicesIndex'])
			->name('site.services.index');
		Route::get('/s/{slug}', [CmsSiteController::class, 'servicesIndex'])
			->name('site.services.category');
		Route::get('/ser/{slug}', [CmsSiteController::class, 'service'])
			->name('site.services.show');
		Route::get('/pj', [CmsSiteController::class, 'projectsIndex'])
			->name('site.projects.index');
		Route::get('/pj/{slug}', [CmsSiteController::class, 'projectsIndex'])
			->name('site.projects.category');
		Route::get('/prj/{slug}', [CmsSiteController::class, 'project'])
			->name('site.projects.show');
		Route::get('/contact', [CmsSiteController::class, 'contact'])
			->name('site.contact');
		Route::post('/contact', [CmsSiteController::class, 'submitContact'])
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
