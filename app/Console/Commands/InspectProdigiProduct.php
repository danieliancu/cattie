<?php

namespace App\Console\Commands;

use App\Integrations\Prodigi\Exceptions\ProdigiException;
use App\Integrations\Prodigi\ProdigiProducts;
use Illuminate\Console\Command;

class InspectProdigiProduct extends Command
{
    protected $signature = 'prodigi:product {sku}';

    protected $description = 'Inspect a product through the configured Prodigi API';

    public function handle(ProdigiProducts $products): int
    {
        try {
            $product = $products->get((string) $this->argument('sku'));
        } catch (ProdigiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('SKU', $product->sku);
        $this->components->twoColumnDetail('Description', $product->description);
        $dimensions = $product->width !== null && $product->height !== null ? "$product->width × $product->height $product->dimensionUnits" : 'Not supplied';
        $this->components->twoColumnDetail('Product dimensions', $dimensions);
        foreach ($product->attributes as $name => $values) {
            $this->components->twoColumnDetail("Attribute: $name", implode(', ', $values));
        }

        $rows = [];
        foreach ($product->variants as $variant) {
            $attributes = collect($variant->attributes)->map(fn ($value, $key) => "$key=$value")->implode(', ');
            $areas = collect($variant->printAreas)->map(fn ($area) => $area->name.': '.($area->horizontalResolution ?? '?').'×'.($area->verticalResolution ?? '?').' px')->implode('; ');
            $rows[] = [$attributes, in_array('GB', $variant->shipsTo, true) ? 'yes' : 'no', $areas];
        }
        $this->table(['Variant attributes', 'Ships to GB', 'Required print areas'], $rows);

        return self::SUCCESS;
    }
}
