<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\WebhookStatus;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

final class TreatPodOrderWebhookController extends Controller
{
    public function __invoke(Request $request, string $event): JsonResponse
    {
        $rawPayload = $request->getContent();
        $authenticationFailure = $this->authenticationFailure($request, $rawPayload);

        if ($authenticationFailure !== null) {
            return response()->json(['message' => $authenticationFailure], 401);
        }

        $payload = $this->payload($request, $rawPayload);
        $externalEventId = $this->externalEventId($request, $event, $rawPayload);

        try {
            WebhookEvent::query()->create([
                'provider' => 'treatpod',
                'external_event_id' => $externalEventId,
                'event_type' => 'order.'.$event,
                'payload' => [
                    'body' => $payload,
                    'meta' => [
                        'content_type' => $request->header('Content-Type'),
                        'user_agent' => $request->userAgent(),
                    ],
                ],
                'status' => WebhookStatus::Received,
                'received_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->alreadyReceived($externalEventId)) {
                throw $exception;
            }
        }

        return response()->json(['received' => true]);
    }

    private function authenticationFailure(Request $request, string $rawPayload): ?string
    {
        $appId = config('services.treatpod.app_id');
        $secretKey = config('services.treatpod.secret_key');

        if (! is_string($appId) || $appId === '' || ! is_string($secretKey) || $secretKey === '') {
            return 'TreatPod webhook authentication is not configured.';
        }

        $providedAppId = $request->query('AppId');
        $providedSignature = $request->query('Signature');

        if (! is_string($providedAppId) || ! hash_equals($appId, $providedAppId)) {
            return 'Invalid TreatPod webhook credentials.';
        }

        $expectedSignature = sha1($rawPayload.$secretKey);

        if (! is_string($providedSignature) || ! hash_equals($expectedSignature, strtolower($providedSignature))) {
            return 'Invalid TreatPod webhook credentials.';
        }

        return null;
    }

    private function payload(Request $request, string $rawPayload): mixed
    {
        if ($rawPayload === '') {
            return $request->all();
        }

        try {
            return json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $request->all() ?: ['raw' => $rawPayload];
        }
    }

    private function externalEventId(Request $request, string $event, string $rawPayload): string
    {
        foreach (['X-Webhook-Id', 'X-Event-Id', 'Webhook-Id'] as $header) {
            if ($value = $request->header($header)) {
                return (string) $value;
            }
        }

        return hash('sha256', $event."\n".$rawPayload);
    }

    private function alreadyReceived(string $externalEventId): bool
    {
        return WebhookEvent::query()
            ->where('provider', 'treatpod')
            ->where('external_event_id', $externalEventId)
            ->exists();
    }
}
