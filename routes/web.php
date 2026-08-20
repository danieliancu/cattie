<?php

use App\Http\Controllers\Storefront\AddressAutocompleteController;
use App\Http\Controllers\Storefront\AddressLookupController;
use App\Http\Controllers\Storefront\ArtworkController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AccountSecurityController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CustomerProfileController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\GoogleAuthController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderRecoveryController;
use App\Http\Controllers\Storefront\InformationPageController;
use App\Http\Controllers\Storefront\CatalogueController;
use App\Http\Controllers\Storefront\LegacyCollectionRedirectController;
use App\Http\Controllers\Storefront\ProductController;
use App\Support\ReservedSlugs;
use App\Http\Controllers\Storefront\OrderSupportController;
use App\Http\Controllers\Admin\OrderSupportPhotoController;
use App\Http\Controllers\Storefront\ProductPreviewController;
use App\Http\Controllers\Storefront\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [CustomerAuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    // Email-verification screen: reachable while signed in but not yet verified.
    Route::get('/register/verify', [CustomerAuthController::class, 'verifyForm'])->name('verification.notice');
    Route::post('/register/verify', [CustomerAuthController::class, 'verify'])->middleware('throttle:10,1')->name('register.verify.store');
    Route::post('/register/verify/resend', [CustomerAuthController::class, 'resend'])->middleware('throttle:3,1')->name('register.verify.resend');

    Route::middleware('verified')->group(function () {
        Route::get('/account', [AccountController::class, 'index'])->name('account.index');
        Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders.index');
        Route::get('/account/orders/{orderNumber}', [AccountController::class, 'show'])->name('account.orders.show');
        Route::get('/account/orders/{orderNumber}/items/{item}/artwork', [AccountController::class, 'artwork'])->name('account.orders.artwork');
        Route::get('/account/details', [CustomerProfileController::class, 'edit'])->name('account.details');
        Route::patch('/account/details', [CustomerProfileController::class, 'update'])->middleware('throttle:60,1')->name('account.details.update');
        Route::put('/account/password', [AccountSecurityController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password.update');
        Route::delete('/account', [AccountSecurityController::class, 'destroy'])->middleware('throttle:10,1')->name('account.destroy');
    });
});
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/admin-preview/products/{product}', ProductPreviewController::class)->name('admin.products.preview');
Route::get('/collections/{slug}', LegacyCollectionRedirectController::class)->where('slug', ReservedSlugs::SLUG_PATTERN)->name('categories.show');
Route::get('/sitemap', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap.xml');
Route::get('/terms-and-conditions', InformationPageController::class)->defaults('page', 'terms-and-conditions')->name('information.terms');
Route::get('/faq', InformationPageController::class)->defaults('page', 'faq')->name('information.faq');
Route::get('/delivery-shipping', InformationPageController::class)->defaults('page', 'delivery-shipping')->name('information.delivery');
Route::get('/returns-policy', InformationPageController::class)->defaults('page', 'returns-policy')->name('information.returns');
Route::get('/privacy-policy', InformationPageController::class)->defaults('page', 'privacy-policy')->name('information.privacy');
Route::get('/manage-cookies', InformationPageController::class)->defaults('page', 'manage-cookies')->name('information.cookies');
Route::get('/payment-methods', InformationPageController::class)->defaults('page', 'payment-methods')->name('information.payments');
Route::post('/products/{product:slug}/artwork', [ArtworkController::class, 'start'])->middleware('throttle:10,1')->name('artwork.start');
Route::get('/artwork/{publicId}', [ArtworkController::class, 'show'])->name('artwork.show');
Route::post('/artwork/{publicId}/upload', [ArtworkController::class, 'upload'])->middleware('throttle:30,1')->name('artwork.upload');
Route::get('/artwork/{publicId}/status', [ArtworkController::class, 'status'])->middleware('throttle:30,1')->name('artwork.status');
Route::get('/artwork/{publicId}/original', [ArtworkController::class, 'original'])->name('artwork.original');
Route::post('/artwork/{publicId}/cancel', [ArtworkController::class, 'cancel'])->middleware('throttle:10,1')->name('artwork.cancel');
Route::get('/artwork/{publicId}/assets/{asset}', [ArtworkController::class, 'asset'])->name('artwork.assets');
Route::get('/artwork/{publicId}/designs/{design}', [ArtworkController::class, 'design'])->name('artwork.designs');
Route::get('/artwork/{publicId}/designs/{design}/editor-background', [ArtworkController::class, 'designEditorBackground'])->name('artwork.design-editor-background');
Route::post('/artwork/{publicId}/designs/{design}/layout', [ArtworkController::class, 'designLayout'])->middleware('throttle:30,1')->name('artwork.design-layout');
Route::post('/artwork/{publicId}/variant', [ArtworkController::class, 'variant'])->name('artwork.variant');
Route::post('/artwork/{publicId}/name', [ArtworkController::class, 'name'])->middleware('throttle:60,1')->name('artwork.name');
Route::post('/artwork/{publicId}/regenerate', [ArtworkController::class, 'regenerate'])->middleware('throttle:5,1')->name('artwork.regenerate');
Route::post('/artwork/{publicId}/approve', [ArtworkController::class, 'approve'])->name('artwork.approve');
Route::post('/artwork/{publicId}/change', [ArtworkController::class, 'change'])->name('artwork.change');
Route::post('/artwork/{publicId}/cart', [CartController::class, 'add'])->name('artwork.cart');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::patch('/cart/items/{item}/quantity', [CartController::class, 'quantity'])->name('cart.quantity');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/items/{item}/change-artwork', [CartController::class, 'changeArtwork'])->name('cart.change-artwork');
Route::get('/address-lookup', AddressLookupController::class)->middleware('throttle:20,1')->name('address-lookup');
Route::get('/address-autocomplete', [AddressAutocompleteController::class, 'suggest'])->middleware('throttle:60,1')->name('address-autocomplete');
Route::get('/address-autocomplete/{placeId}', [AddressAutocompleteController::class, 'resolve'])->middleware('throttle:60,1')->name('address-autocomplete.resolve');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/{orderNumber}/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
Route::post('/checkout/{orderNumber}/payment', [CheckoutController::class, 'pay'])->middleware('throttle:10,1')->name('checkout.pay');
Route::post('/checkout/{orderNumber}/payment/stripe-session', [CheckoutController::class, 'stripeSession'])->middleware('throttle:10,1')->name('checkout.stripe-session');
Route::post('/checkout/{orderNumber}/payment/stripe-status', [CheckoutController::class, 'stripeStatus'])->middleware('throttle:60,1')->name('checkout.stripe-status');
Route::get('/checkout/{orderNumber}/payment/stripe-return', [CheckoutController::class, 'stripeReturn'])->name('checkout.stripe-return');
Route::get('/orders/{orderNumber}/confirmation', [CheckoutController::class, 'confirmation'])->name('orders.confirmation');
// Signed links from abandoned-checkout recovery emails.
Route::get('/orders/{order}/resume', [OrderRecoveryController::class, 'resume'])->middleware('signed')->name('checkout.resume');
Route::get('/orders/{order}/stop-reminders', [OrderRecoveryController::class, 'stopReminders'])->middleware('signed')->name('orders.stop-reminders');
Route::get('/order-support', [OrderSupportController::class, 'create'])->name('order-support.create');
Route::post('/order-support', [OrderSupportController::class, 'store'])->middleware('throttle:10,60')->name('order-support.store');
Route::get('/order-support/submitted', [OrderSupportController::class, 'submitted'])->name('order-support.submitted');
Route::get('/admin/order-support/{orderSupportRequest}/photo', [OrderSupportPhotoController::class, 'show'])->middleware('auth')->name('admin.order-support.photo');

/*
|--------------------------------------------------------------------------
| Catalogue taxonomy — MUST stay last
|--------------------------------------------------------------------------
|
| Top-level categories own the first path segment, so these patterns are the
| only thing standing between /products/foo and a category lookup. The slug
| constraint excludes every reserved application segment, which means the
| router simply does not match here and falls through to the real route rather
| than resolving and 404ing inside the controller.
|
*/
Route::get('/{categorySlug}', [CatalogueController::class, 'category'])
    ->where('categorySlug', ReservedSlugs::routePattern())
    ->name('catalogue.category');

Route::get('/{categorySlug}/{subcategorySlug}', [CatalogueController::class, 'subcategory'])
    ->where(['categorySlug' => ReservedSlugs::routePattern(), 'subcategorySlug' => ReservedSlugs::SLUG_PATTERN])
    ->name('catalogue.subcategory');
