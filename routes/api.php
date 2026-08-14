<?php

use App\Http\Controllers\Webhooks\TreatPodOrderWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/treatpod/orders/{event}', TreatPodOrderWebhookController::class)
    ->whereIn('event', ['creation', 'deletion', 'shipped', 'payment', 'updated'])
    ->middleware('throttle:120,1')
    ->name('webhooks.treatpod.orders');
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.stripe');
