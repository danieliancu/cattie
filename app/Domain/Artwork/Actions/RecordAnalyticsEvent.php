<?php

namespace App\Domain\Artwork\Actions;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Model;

class RecordAnalyticsEvent
{
    public function handle(string $name, Model $subject, array $properties = []): void
    {
        AnalyticsEvent::query()->create(['name' => $name, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'properties' => $properties, 'occurred_at' => now()]);
    }
}
