<?php

namespace App\Integrations\Prodigi;

use App\Integrations\Prodigi\Data\PrintArea;
use App\Integrations\Prodigi\Data\ProductDetails;
use App\Integrations\Prodigi\Data\ProductVariant;
use App\Integrations\Prodigi\Exceptions\ProdigiResponseException;

class ProdigiProducts
{
    public function __construct(private readonly ProdigiClient $client) {}

    public function get(string $sku): ProductDetails
    {
        $body = $this->client->get('/v4.0/products/'.rawurlencode($sku), ['sku' => $sku]);
        $product = $body['product'] ?? null;
        if (! is_array($product) || ! is_string($product['sku'] ?? null) || ! is_array($product['variants'] ?? null)) {
            throw new ProdigiResponseException('Prodigi Product Details response is missing required fields.', 'malformed');
        }

        $requiredAreas = [];
        foreach (($product['printAreas'] ?? []) as $name => $area) {
            if (is_string($name) && is_array($area)) {
                $requiredAreas[] = new PrintArea($name, (bool) ($area['required'] ?? false));
            }
        }

        $variants = [];
        foreach ($product['variants'] as $variant) {
            if (! is_array($variant) || ! is_array($variant['attributes'] ?? null)) {
                throw new ProdigiResponseException('Prodigi returned a malformed product variant.', 'malformed');
            }
            $areas = [];
            foreach (($variant['printAreaSizes'] ?? []) as $name => $size) {
                if (is_string($name) && is_array($size)) {
                    $required = collect($requiredAreas)->firstWhere('name', $name)?->required ?? false;
                    $areas[] = new PrintArea($name, $required, $this->integer($size['horizontalResolution'] ?? null), $this->integer($size['verticalResolution'] ?? null));
                }
            }
            $attributes = array_filter($variant['attributes'], fn ($value) => is_string($value));
            $shipsTo = array_values(array_filter($variant['shipsTo'] ?? [], fn ($value) => is_string($value)));
            $variants[] = new ProductVariant($attributes, $shipsTo, $areas);
        }

        $dimensions = is_array($product['productDimensions'] ?? null) ? $product['productDimensions'] : [];
        $attributes = [];
        foreach (($product['attributes'] ?? []) as $name => $values) {
            if (is_string($name) && is_array($values)) {
                $attributes[$name] = array_values(array_filter($values, fn ($value) => is_string($value)));
            }
        }

        return new ProductDetails(
            $product['sku'],
            is_string($product['description'] ?? null) ? $product['description'] : '',
            is_numeric($dimensions['width'] ?? null) ? (float) $dimensions['width'] : null,
            is_numeric($dimensions['height'] ?? null) ? (float) $dimensions['height'] : null,
            is_string($dimensions['units'] ?? null) ? $dimensions['units'] : null,
            $attributes,
            $requiredAreas,
            $variants,
        );
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
