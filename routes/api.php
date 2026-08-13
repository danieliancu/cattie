<?php

use App\Http\Controllers\Webhooks\TreatPodOrderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/treatpod/orders/{event}', TreatPodOrderWebhookController::class)
    ->whereIn('event', ['creation', 'deletion', 'shipped', 'payment', 'updated'])
    ->middleware('throttle:120,1')
    ->name('webhooks.treatpod.orders');
