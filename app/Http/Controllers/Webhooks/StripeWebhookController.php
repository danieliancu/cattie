<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\Actions\ReconcilePayment;
use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Providers\Payments\StripePaymentProvider;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripePaymentProvider $stripe, ReconcilePayment $reconcile): JsonResponse
    {
        try {
            $notification = $stripe->parseWebhook($request->getContent(), $request->headers->all());
        } catch (Throwable) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $eventId = $notification['event_id'] ?? null;
        $eventType = $notification['event_type'] ?? null;
        if (! is_string($eventId) || ! is_string($eventType)) {
            return response()->json(['message' => 'Invalid Stripe webhook event.'], 400);
        }
        $supported = ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'];
        $result = $notification['result'];
        $payload = [
            'checkout_session_id' => $result->providerReference,
            'payment_status' => $result->status->value,
            'amount_total' => $result->amountMinor,
            'currency' => $result->currency,
            'metadata' => $result->metadata,
        ];

        try {
            $event = WebhookEvent::query()->create([
                'provider' => 'stripe', 'external_event_id' => $eventId, 'event_type' => $eventType,
                'payload' => $payload, 'status' => WebhookStatus::Received, 'received_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (WebhookEvent::query()->where('provider', 'stripe')->where('external_event_id', $eventId)->exists()) {
                return response()->json(['received' => true, 'duplicate' => true]);
            }
            throw $exception;
        }

        if (! in_array($eventType, $supported, true)) {
            $event->update(['status' => WebhookStatus::Ignored, 'processed_at' => now()]);

            return response()->json(['received' => true]);
        }

        $event->update(['status' => WebhookStatus::Processing]);
        try {
            $reconcile->handle($result);
            $event->update(['status' => WebhookStatus::Processed, 'processed_at' => now()]);
        } catch (ValidationException $exception) {
            $event->update(['status' => WebhookStatus::Failed, 'failure_reason' => $exception->getMessage(), 'processed_at' => now()]);

            return response()->json(['message' => 'Stripe payment reconciliation failed.'], 422);
        } catch (Throwable $exception) {
            report($exception);
            $event->update(['status' => WebhookStatus::Failed, 'failure_reason' => 'Unexpected reconciliation failure.', 'processed_at' => now()]);

            return response()->json(['message' => 'Stripe webhook processing failed.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
