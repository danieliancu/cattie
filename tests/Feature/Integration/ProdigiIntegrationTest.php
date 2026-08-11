<?php

namespace Tests\Feature\Integration;

use App\Integrations\Prodigi\Exceptions\ProdigiAuthenticationException;
use App\Integrations\Prodigi\Exceptions\ProdigiConfigurationException;
use App\Integrations\Prodigi\Exceptions\ProdigiNetworkException;
use App\Integrations\Prodigi\Exceptions\ProdigiNotFoundException;
use App\Integrations\Prodigi\Exceptions\ProdigiResponseException;
use App\Integrations\Prodigi\Exceptions\ProdigiValidationException;
use App\Integrations\Prodigi\ProdigiProducts;
use App\Integrations\Prodigi\ProdigiQuotes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProdigiIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['prodigi.base_url' => 'https://api.sandbox.prodigi.test', 'prodigi.api_key' => 'sandbox-secret', 'prodigi.timeout' => 7]);
    }

    public function test_product_details_are_authenticated_and_mapped(): void
    {
        Http::fake(['*/v4.0/products/*' => Http::response($this->productResponse())]);

        $product = app(ProdigiProducts::class)->get('650ML-WATER-BOTTLE');

        $this->assertSame('650ML-WATER-BOTTLE', $product->sku);
        $this->assertSame(23.0, $product->width);
        $this->assertSame(['black', 'white / clear'], $product->attributes['color']);
        $this->assertSame(2750, $product->variants[0]->printAreas[0]->horizontalResolution);
        $this->assertSame(1828, $product->variants[1]->printAreas[0]->verticalResolution);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.prodigi.test/v4.0/products/650ML-WATER-BOTTLE'
            && $request->hasHeader('X-API-Key', 'sandbox-secret')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_quote_uses_a_variant_returned_by_product_details(): void
    {
        Http::fake([
            '*/v4.0/products/*' => Http::response($this->productResponse()),
            '*/v4.0/quotes' => Http::response($this->quoteResponse()),
        ]);

        $quote = app(ProdigiQuotes::class)->create('650ML-WATER-BOTTLE', 'gb', 1, ['color' => 'black']);

        $this->assertSame(['color' => 'black', 'size' => '650ml / 22oz'], $quote->attributes);
        $this->assertSame('12.34', $quote->options[0]->total->amount);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v4.0/quotes')
            && $request['destinationCountryCode'] === 'GB'
            && $request['currencyCode'] === 'GBP'
            && ! isset($request['shippingMethod'])
            && $request['items'][0] === ['sku' => '650ML-WATER-BOTTLE', 'copies' => 1, 'attributes' => ['color' => 'black', 'size' => '650ml / 22oz'], 'assets' => [['printArea' => 'default']]]);
    }

    public function test_invalid_or_non_shipping_attributes_never_request_a_quote(): void
    {
        Http::fake(['*/v4.0/products/*' => Http::response($this->productResponse())]);

        $this->expectException(ProdigiValidationException::class);
        try {
            app(ProdigiQuotes::class)->create('650ML-WATER-BOTTLE', 'US', 1, ['color' => 'black']);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_api_failures_are_typed(): void
    {
        Http::fakeSequence()
            ->push(['issues' => [['code' => 'safe-code', 'description' => 'Safe provider message']]], 401)
            ->push(['issues' => [['code' => 'safe-code', 'description' => 'Safe provider message']]], 404)
            ->push(['issues' => [['code' => 'safe-code', 'description' => 'Safe provider message']]], 422)
            ->push(['issues' => [['code' => 'safe-code', 'description' => 'Safe provider message']]], 500);

        foreach ([
            [401, ProdigiAuthenticationException::class],
            [404, ProdigiNotFoundException::class],
            [422, ProdigiValidationException::class],
            [500, ProdigiResponseException::class],
        ] as [$status, $exception]) {
            try {
                app(ProdigiProducts::class)->get('BAD-SKU');
                $this->fail("Status $status did not throw.");
            } catch (\Throwable $thrown) {
                $this->assertInstanceOf($exception, $thrown);
            }
        }
    }

    public function test_failure_logs_contain_safe_context_but_never_the_api_key(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['issues' => [['code' => 'provider-error', 'description' => 'Safe message']]], 500)]);

        try {
            app(ProdigiProducts::class)->get('SAFE-SKU');
        } catch (ProdigiResponseException) {
            // Expected.
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $encoded = json_encode([$message, $context]);

            return $context['sku'] === 'SAFE-SKU'
                && $context['status'] === 500
                && $context['provider_code'] === 'provider-error'
                && ! str_contains($encoded, 'sandbox-secret');
        });
    }

    public function test_missing_key_timeout_and_malformed_json_are_handled(): void
    {
        config(['prodigi.api_key' => null]);
        $this->expectException(ProdigiConfigurationException::class);
        app(ProdigiProducts::class)->get('SKU');
    }

    public function test_network_timeout_is_typed(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->expectException(ProdigiNetworkException::class);
        app(ProdigiProducts::class)->get('SKU');
    }

    public function test_malformed_json_is_typed(): void
    {
        Http::fake(['*' => Http::response('not json', 200)]);
        $this->expectException(ProdigiResponseException::class);
        app(ProdigiProducts::class)->get('SKU');
    }

    public function test_commands_summarise_without_exposing_the_api_key(): void
    {
        Log::spy();
        Http::fake([
            '*/v4.0/products/*' => Http::response($this->productResponse()),
            '*/v4.0/quotes' => Http::response($this->quoteResponse()),
        ]);

        $this->artisan('prodigi:product 650ML-WATER-BOTTLE')->assertSuccessful()->expectsOutputToContain('2750×2279 px')->doesntExpectOutput('sandbox-secret');
        $this->artisan('prodigi:quote 650ML-WATER-BOTTLE')->assertSuccessful()->expectsOutputToContain('GBP 12.34')->doesntExpectOutput('sandbox-secret');
        Log::shouldNotHaveReceived('warning');
    }

    private function productResponse(): array
    {
        return ['outcome' => 'Ok', 'product' => [
            'sku' => '650ML-WATER-BOTTLE',
            'description' => 'Personalised bottle',
            'productDimensions' => ['width' => 23, 'height' => 10, 'units' => 'cm'],
            'attributes' => ['color' => ['black', 'white / clear'], 'size' => ['650ml / 22oz']],
            'printAreas' => ['default' => ['required' => true]],
            'variants' => [
                ['attributes' => ['color' => 'black', 'size' => '650ml / 22oz'], 'shipsTo' => ['GB'], 'printAreaSizes' => ['default' => ['horizontalResolution' => 2750, 'verticalResolution' => 2279]]],
                ['attributes' => ['color' => 'white / clear', 'size' => '650ml / 22oz'], 'shipsTo' => ['GB'], 'printAreaSizes' => ['default' => ['horizontalResolution' => 2498, 'verticalResolution' => 1828]]],
            ],
        ]];
    }

    private function quoteResponse(): array
    {
        return ['outcome' => 'Created', 'quotes' => [[
            'shipmentMethod' => 'Budget',
            'costSummary' => ['items' => ['amount' => '10.00', 'currency' => 'GBP'], 'shipping' => ['amount' => '2.34', 'currency' => 'GBP']],
            'shipments' => [['carrier' => ['name' => 'royalmail', 'service' => 'Standard'], 'fulfillmentLocation' => ['countryCode' => 'GB', 'labCode' => 'uk1']]],
        ]]];
    }
}
