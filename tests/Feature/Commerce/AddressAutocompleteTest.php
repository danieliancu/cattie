<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['address_lookup.google_places.api_key' => 'test-key']);
    }

    public function test_suggest_returns_mapped_suggestions_for_free_text_input(): void
    {
        Http::fake(['places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [[
                'placePrediction' => ['placeId' => 'abc123', 'text' => ['text' => '10 Downing Street, London, UK']],
            ]],
        ])]);

        $this->getJson(route('address-autocomplete', ['q' => '10 Downing Street']))
            ->assertOk()->assertExactJson(['suggestions' => [['id' => 'abc123', 'description' => '10 Downing Street, London, UK']]]);
    }

    public function test_suggest_gracefully_returns_empty_list_when_provider_fails(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->getJson(route('address-autocomplete', ['q' => '10 Downing Street']))
            ->assertOk()->assertExactJson(['suggestions' => []]);
    }

    public function test_suggest_gracefully_returns_empty_list_without_an_api_key(): void
    {
        config(['address_lookup.google_places.api_key' => null]);
        Http::fake();

        $this->getJson(route('address-autocomplete', ['q' => '10 Downing Street']))
            ->assertOk()->assertExactJson(['suggestions' => []]);

        Http::assertNothingSent();
    }

    public function test_resolve_returns_structured_address_for_a_chosen_place(): void
    {
        Http::fake(['places.googleapis.com/v1/places/abc123' => Http::response([
            'addressComponents' => [
                ['longText' => '10', 'types' => ['street_number']],
                ['longText' => 'Downing Street', 'types' => ['route']],
                ['longText' => 'London', 'types' => ['postal_town']],
                ['longText' => 'Greater London', 'types' => ['administrative_area_level_2']],
                ['longText' => 'SW1A 2AA', 'types' => ['postal_code']],
            ],
        ])]);

        $this->getJson(route('address-autocomplete.resolve', ['placeId' => 'abc123']))
            ->assertOk()->assertExactJson([
                'resolved' => true, 'address_line_1' => '10 Downing Street', 'address_line_2' => null,
                'city' => 'London', 'county' => 'Greater London', 'postcode' => 'SW1A 2AA', 'country' => 'GB',
            ]);
    }

    public function test_resolve_returns_unresolved_when_provider_fails(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->getJson(route('address-autocomplete.resolve', ['placeId' => 'abc123']))
            ->assertOk()->assertExactJson(['resolved' => false]);
    }

    public function test_suggest_sends_only_the_query_text_no_customer_pii(): void
    {
        Http::fake(['places.googleapis.com/*' => Http::response(['suggestions' => []])]);

        $this->getJson(route('address-autocomplete', ['q' => '10 Downing Street']))->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://places.googleapis.com/v1/places:autocomplete'
                && ($request['input'] ?? null) === '10 Downing Street'
                && ! str_contains(json_encode($request->data()), '@');
        });
    }
}
