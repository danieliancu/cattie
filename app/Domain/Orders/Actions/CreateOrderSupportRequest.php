<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Artwork\Actions\RecordAnalyticsEvent;
use App\Enums\OrderSupportStatus;
use App\Models\Order;
use App\Models\OrderSupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateOrderSupportRequest
{
    public function __construct(private RecordAnalyticsEvent $analytics) {}

    /**
     * @param  array{bytes: string, mime: string, extension: string}|null  $photo
     */
    public function handle(Order $order, ?User $user, string $contactEmail, string $message, ?array $photo): OrderSupportRequest
    {
        $id = (string) Str::ulid();
        $photoFields = [];

        if ($photo) {
            $key = "order-support/{$id}/photo.{$photo['extension']}";
            if (! Storage::disk('local')->put($key, $photo['bytes'])) {
                throw new RuntimeException("The photo couldn't be uploaded. Please try again.");
            }
            $photoFields = [
                'photo_disk' => 'local',
                'photo_storage_key' => $key,
                'photo_mime_type' => $photo['mime'],
                'photo_size_bytes' => strlen($photo['bytes']),
            ];
        }

        try {
            $request = OrderSupportRequest::query()->create([
                'id' => $id,
                'reference' => $this->uniqueReference(),
                'order_id' => $order->id,
                'user_id' => $user?->id,
                'contact_email' => $contactEmail,
                'message' => $message,
                'status' => OrderSupportStatus::Open,
                ...$photoFields,
            ]);
        } catch (Throwable $e) {
            if (! empty($photoFields)) {
                Storage::disk('local')->delete($photoFields['photo_storage_key']);
            }
            throw $e;
        }

        $this->analytics->handle('order_support_submitted', $order);

        return $request;
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'SUP-'.strtoupper(Str::random(6));
        } while (OrderSupportRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
