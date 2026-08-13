<?php

namespace Tests\Feature\Integration;

use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatPodWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_ID = 'APP-00001234';

    private const SECRET_KEY = 'test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.treatpod.app_id' => self::APP_ID,
            'services.treatpod.secret_key' => self::SECRET_KEY,
        ]);
    }

    public function test_it_records_each_supported_order_webhook(): void
    {
        foreach (['creation', 'deletion', 'shipped', 'payment', 'updated'] as $event) {
            $payload = [
                'id' => 12000 + array_search($event, ['creation', 'deletion', 'shipped', 'payment', 'updated'], true),
                'status' => $event,
            ];

            $this->postJson($this->signedUrl($event, $payload), $payload, ['X-Webhook-Id' => 'event-'.$event])
                ->assertOk()
                ->assertExactJson(['received' => true]);
        }

        $this->assertSame(5, WebhookEvent::query()->where('provider', 'treatpod')->count());
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'treatpod',
            'external_event_id' => 'event-shipped',
            'event_type' => 'order.shipped',
            'status' => WebhookStatus::Received->value,
        ]);
    }

    public function test_duplicate_delivery_is_acknowledged_without_duplicate_storage(): void
    {
        $headers = ['X-Webhook-Id' => 'same-delivery'];

        $payload = ['id' => 123];

        $this->postJson($this->signedUrl('updated', $payload), $payload, $headers)->assertOk();
        $this->postJson($this->signedUrl('updated', $payload), $payload, $headers)->assertOk();

        $this->assertSame(1, WebhookEvent::query()->count());
    }

    public function test_unknown_event_is_not_accepted(): void
    {
        $this->postJson($this->signedUrl('unknown', ['id' => 123]), ['id' => 123])->assertNotFound();

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_invalid_signature_is_rejected_without_storage(): void
    {
        $this->postJson('/api/webhooks/treatpod/orders/creation?AppId='.self::APP_ID.'&Signature='.str_repeat('0', 40), ['id' => 123])
            ->assertUnauthorized();

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_missing_configuration_is_rejected_without_storage(): void
    {
        config(['services.treatpod.app_id' => null, 'services.treatpod.secret_key' => null]);

        $this->postJson('/api/webhooks/treatpod/orders/creation', ['id' => 123])->assertUnauthorized();

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    private function signedUrl(string $event, array $payload): string
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return "/api/webhooks/treatpod/orders/{$event}?".http_build_query([
            'AppId' => self::APP_ID,
            'Signature' => sha1($body.self::SECRET_KEY),
        ]);
    }
}
