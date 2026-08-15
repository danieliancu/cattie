<?php

namespace Tests\Feature\Commerce;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomedataAddressLookupProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['address_lookup.provider' => 'homedata', 'address_lookup.homedata.api_key' => 'test-key']);
    }

    public function test_returns_valid_true_with_addresses_mapped_from_street_and_building_number(): void
    {
        Http::fake(['api.homedata.co.uk/*' => Http::response([
            'postcode' => 'SW1A 2AA', 'count' => 1,
            'addresses' => [[
                'uprn' => 100023336956, 'address' => '10 DOWNING STREET, LONDON, SW1A 2AA',
                'building_name' => '', 'building_number' => '10', 'sub_building' => '',
                'street' => 'Downing Street', 'town' => 'London',
            ]],
        ])]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A2AA']))
            ->assertOk()->assertExactJson(['valid' => true, 'postcode' => 'SW1A 2AA', 'addresses' => [[
                'address_line_1' => '10 Downing Street', 'address_line_2' => null,
                'city' => 'London', 'county' => null, 'postcode' => 'SW1A 2AA', 'country' => 'GB',
            ]]]);
    }

    public function test_maps_sub_building_and_building_name_to_address_line_1_with_street_on_line_2(): void
    {
        Http::fake(['api.homedata.co.uk/*' => Http::response([
            'postcode' => 'SW1A 2AA', 'count' => 1,
            'addresses' => [[
                'building_name' => 'The Old Mill', 'building_number' => '12', 'sub_building' => 'Flat 2',
                'street' => 'Downing Street', 'town' => 'London',
            ]],
        ])]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A2AA']))->assertOk()
            ->assertJson(['addresses' => [[
                'address_line_1' => 'Flat 2, The Old Mill', 'address_line_2' => '12 Downing Street', 'city' => 'London',
            ]]]);
    }

    public function test_missing_api_key_gracefully_returns_invalid_without_calling_provider(): void
    {
        config(['address_lookup.homedata.api_key' => null]);
        Http::fake();

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A2AA']))
            ->assertOk()->assertJson(['valid' => false, 'addresses' => []]);

        Http::assertNothingSent();
    }

    public function test_provider_failure_gracefully_returns_invalid_without_a_500(): void
    {
        Http::fake(['api.homedata.co.uk/*' => Http::response(['error' => 'Internal Server Error'], 500)]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A2AA']))
            ->assertOk()->assertJson(['valid' => false, 'addresses' => []]);
    }

    public function test_sends_only_the_postcode_and_api_key_no_other_customer_data(): void
    {
        Http::fake(['api.homedata.co.uk/*' => Http::response(['postcode' => 'SW1A 2AA', 'addresses' => []])]);

        $this->getJson(route('address-lookup', ['postcode' => 'SW1A2AA']))->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'SW1A2AA')
                && $request->hasHeader('Authorization', 'Api-Key test-key')
                && ! str_contains($request->url(), '@');
        });
    }
}
