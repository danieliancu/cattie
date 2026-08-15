<?php

namespace Tests\Feature\Commerce;

use App\Support\UkPostcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AddressLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate from whatever ADDRESS_LOOKUP_PROVIDER a developer's local .env happens to have
        // set (e.g. while testing a different provider manually) — this file specifically exercises
        // the postcodes_io-shaped provider and endpoint contract.
        config(['address_lookup.provider' => 'postcodes_io']);
    }

    public function test_uk_postcode_normalizer_strips_spaces_and_uppercases_for_validation_input(): void
    {
        $this->assertSame('SW1A1AA', UkPostcode::normalizeForInput(' sw1a 1aa '));
    }

    public function test_uk_postcode_normalizer_reinserts_canonical_space_before_inward_code(): void
    {
        $this->assertSame('SW1A 1AA', UkPostcode::format('SW1A1AA'));
    }

    public function test_address_lookup_endpoint_returns_valid_true_for_a_known_postcode(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response([
            'status' => 200,
            'result' => ['postcode' => 'SW1A 1AA', 'region' => 'London', 'admin_district' => 'Westminster', 'country' => 'England'],
        ])]);

        $this->getJson(route('address-lookup', ['postcode' => 'sw1a 1aa']))
            ->assertOk()->assertExactJson(['valid' => true, 'postcode' => 'SW1A 1AA', 'addresses' => []]);
    }

    public function test_address_lookup_endpoint_returns_valid_false_for_a_malformed_postcode_without_calling_the_provider(): void
    {
        Http::fake();

        $this->getJson(route('address-lookup', ['postcode' => 'not-a-postcode']))
            ->assertOk()->assertExactJson(['valid' => false, 'postcode' => null, 'addresses' => []]);

        Http::assertNothingSent();
    }

    public function test_address_lookup_endpoint_gracefully_handles_provider_timeout_or_5xx_without_a_500(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response(['error' => 'Internal Server Error'], 500)]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A1AA']))
            ->assertOk()->assertJson(['valid' => false, 'addresses' => []]);
    }

    public function test_address_lookup_endpoint_is_rate_limited(): void
    {
        RateLimiter::clear('address-lookup');
        Http::fake(['api.postcodes.io/*' => Http::response(['result' => []])]);

        for ($i = 0; $i < 20; $i++) {
            $this->getJson(route('address-lookup', ['postcode' => 'SW1A1AA']))->assertOk();
        }

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A1AA']))->assertStatus(429);
    }

    public function test_address_lookup_never_sends_more_than_the_postcode_to_the_provider(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response(['result' => []])]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A1AA']))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'SW1A1AA')
                && ! str_contains($request->url(), '@')
                && $request->method() === 'GET';
        });
    }
}
