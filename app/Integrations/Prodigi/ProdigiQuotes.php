<?php

namespace App\Integrations\Prodigi;

use App\Integrations\Prodigi\Data\Money;
use App\Integrations\Prodigi\Data\ProductVariant;
use App\Integrations\Prodigi\Data\QuoteOption;
use App\Integrations\Prodigi\Data\QuoteResult;
use App\Integrations\Prodigi\Exceptions\ProdigiResponseException;
use App\Integrations\Prodigi\Exceptions\ProdigiValidationException;

class ProdigiQuotes
{
    public function __construct(private readonly ProdigiClient $client, private readonly ProdigiProducts $products) {}

    /** @param array<string, string> $requestedAttributes */
    public function create(string $sku, string $country, int $quantity = 1, array $requestedAttributes = [], string $currency = 'GBP'): QuoteResult
    {
        $product = $this->products->get($sku);
        $country = strtoupper($country);
        $variant = $this->selectVariant($product->variants, $requestedAttributes, $country);
        $assets = array_map(fn ($area) => ['printArea' => $area->name], array_values(array_filter($variant->printAreas, fn ($area) => $area->required)));
        if ($assets === []) {
            $assets = array_map(fn ($area) => ['printArea' => $area->name], $product->printAreas);
        }

        $body = $this->client->post('/v4.0/quotes', [
            'destinationCountryCode' => $country,
            'currencyCode' => strtoupper($currency),
            'items' => [[
                'sku' => $product->sku,
                'copies' => $quantity,
                'attributes' => $variant->attributes,
                'assets' => $assets,
            ]],
        ], ['sku' => $product->sku]);

        if (! is_array($body['quotes'] ?? null)) {
            throw new ProdigiResponseException('Prodigi Quote response is missing quotes.', 'malformed');
        }

        $options = array_map(fn ($quote) => $this->mapQuote($quote), $body['quotes']);

        return new QuoteResult($product->sku, $variant->attributes, $quantity, $country, $options);
    }

    /** @param list<ProductVariant> $variants @param array<string, string> $attributes */
    private function selectVariant(array $variants, array $attributes, string $country): ProductVariant
    {
        foreach ($variants as $variant) {
            $matches = collect($attributes)->every(fn ($value, $key) => ($variant->attributes[$key] ?? null) === $value);
            if ($matches && in_array($country, $variant->shipsTo, true)) {
                return $variant;
            }
        }

        throw new ProdigiValidationException('No Prodigi variant matches the selected attributes and destination.', 'validation');
    }

    private function mapQuote(mixed $quote): QuoteOption
    {
        if (! is_array($quote) || ! is_string($quote['shipmentMethod'] ?? null)) {
            throw new ProdigiResponseException('Prodigi returned a malformed quote.', 'malformed');
        }
        $items = $this->money(data_get($quote, 'costSummary.items'));
        $shipping = $this->money(data_get($quote, 'costSummary.shipping'));
        if ($items->currency !== $shipping->currency) {
            throw new ProdigiResponseException('Prodigi returned mixed quote currencies.', 'malformed');
        }
        $total = number_format((float) $items->amount + (float) $shipping->amount, 2, '.', '');
        $shipment = is_array($quote['shipments'][0] ?? null) ? $quote['shipments'][0] : [];

        return new QuoteOption($quote['shipmentMethod'], $items, $shipping, new Money($total, $items->currency), data_get($shipment, 'carrier.name'), data_get($shipment, 'carrier.service'), data_get($shipment, 'fulfillmentLocation.countryCode'), data_get($shipment, 'fulfillmentLocation.labCode'));
    }

    private function money(mixed $value): Money
    {
        if (! is_array($value) || ! is_numeric($value['amount'] ?? null) || ! is_string($value['currency'] ?? null)) {
            throw new ProdigiResponseException('Prodigi returned malformed quote costs.', 'malformed');
        }

        return new Money((string) $value['amount'], $value['currency']);
    }
}
