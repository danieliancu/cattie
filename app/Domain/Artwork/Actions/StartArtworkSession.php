<?php

namespace App\Domain\Artwork\Actions;

use App\Enums\ArtworkSessionStatus;
use App\Enums\PersonalisationFieldType;
use App\Models\ArtworkSession;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartArtworkSession
{
    public function __construct(private RecordAnalyticsEvent $analytics) {}

    public function handle(Product $product, array $input, ?string $existingToken = null): array
    {
        $variant = $product->variants()->active()->find($input['variant_id'] ?? null);
        $style = $product->artworkStyles()->find($input['artwork_style_id'] ?? null);
        if (! $variant || ! $style) {
            throw ValidationException::withMessages(['selection' => 'Please choose an available option and artwork style.']);
        } $fields = $product->personalisationFields;
        $known = $fields->pluck('key')->all();
        $submitted = array_keys($input['personalisation'] ?? []);
        if (array_diff($submitted, $known)) {
            throw ValidationException::withMessages(['personalisation' => 'Unknown personalisation field.']);
        } $rules = [];
        foreach ($fields as $field) {
            $rule = $field->is_required ? ['required'] : ['nullable'];
            $rule[] = match ($field->type) {
                PersonalisationFieldType::Date => 'date',default => 'string'
            };
            if ($max = $field->validation_rules['max'] ?? null) {
                $rule[] = 'max:'.$max;
            } if ($field->type === PersonalisationFieldType::Select) {
                $rule[] = 'in:'.implode(',', $field->configuration['options'] ?? []);
            } $rules['personalisation.'.$field->key] = $rule;
        } $values = Validator::make($input, $rules)->validate()['personalisation'] ?? [];
        $snapshot = $fields->map(fn ($field) => ['key' => $field->key, 'label' => $field->label, 'type' => $field->type->value, 'value' => $values[$field->key] ?? null])->all();
        $token = $existingToken ?: Str::random(64);
        $session = ArtworkSession::query()->create(['public_id' => (string) Str::ulid(), 'access_token_hash' => hash('sha256', $token), 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'artwork_style_id' => $style->id, 'personalisation_snapshot' => $snapshot, 'status' => ArtworkSessionStatus::AwaitingUpload, 'expires_at' => now()->addDays(config('artwork.retention_days'))]);
        $this->analytics->handle('artwork_session_started', $session);

        return [$session, $token];
    }
}
