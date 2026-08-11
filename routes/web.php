<?php

use App\Http\Controllers\Storefront\ArtworkController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product:slug}/artwork', [ArtworkController::class, 'start'])->middleware('throttle:10,1')->name('artwork.start');
Route::get('/artwork/{publicId}', [ArtworkController::class, 'show'])->name('artwork.show');
Route::post('/artwork/{publicId}/upload', [ArtworkController::class, 'upload'])->middleware('throttle:5,1')->name('artwork.upload');
Route::get('/artwork/{publicId}/status', [ArtworkController::class, 'status'])->middleware('throttle:30,1')->name('artwork.status');
Route::get('/artwork/{publicId}/assets/{asset}', [ArtworkController::class, 'asset'])->name('artwork.assets');
Route::post('/artwork/{publicId}/regenerate', [ArtworkController::class, 'regenerate'])->middleware('throttle:5,1')->name('artwork.regenerate');
Route::post('/artwork/{publicId}/approve', [ArtworkController::class, 'approve'])->name('artwork.approve');
Route::post('/artwork/{publicId}/change', [ArtworkController::class, 'change'])->name('artwork.change');
Route::post('/artwork/{publicId}/cart', [CartController::class, 'add'])->name('artwork.cart');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::patch('/cart/items/{item}/quantity', [CartController::class, 'quantity'])->name('cart.quantity');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/items/{item}/change-artwork', [CartController::class, 'changeArtwork'])->name('cart.change-artwork');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/{orderNumber}/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
Route::post('/checkout/{orderNumber}/payment', [CheckoutController::class, 'pay'])->middleware('throttle:10,1')->name('checkout.pay');
Route::get('/orders/{orderNumber}/confirmation', [CheckoutController::class, 'confirmation'])->name('orders.confirmation');
