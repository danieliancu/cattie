<?php

namespace App\Console\Commands;

use App\Integrations\Prodigi\Exceptions\ProdigiException;
use App\Integrations\Prodigi\ProdigiQuotes;
use Illuminate\Console\Command;

class QuoteProdigiProduct extends Command
{
    protected $signature = 'prodigi:quote {sku} {--country=GB} {--color=black} {--quantity=1}';

    protected $description = 'Request a non-order Prodigi product and shipping quote';

    public function handle(ProdigiQuotes $quotes): int
    {
        $quantity = filter_var($this->option('quantity'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($quantity === false) {
            $this->error('Quantity must be a positive integer.');

            return self::INVALID;
        }

        try {
            $quote = $quotes->create((string) $this->argument('sku'), (string) $this->option('country'), $quantity, ['color' => (string) $this->option('color')], 'GBP');
        } catch (ProdigiException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('SKU', $quote->sku);
        $this->components->twoColumnDetail('Destination', $quote->destinationCountry);
        $this->components->twoColumnDetail('Quantity', (string) $quote->quantity);
        $this->components->twoColumnDetail('Selected attributes', collect($quote->attributes)->map(fn ($value, $key) => "$key=$value")->implode(', '));
        $this->table(['Method', 'Items', 'Shipping', 'Total', 'Carrier / service', 'Fulfilled by'], array_map(fn ($option) => [
            $option->shippingMethod,
            $option->items->currency.' '.$option->items->amount,
            $option->shipping->currency.' '.$option->shipping->amount,
            $option->total->currency.' '.$option->total->amount,
            trim(($option->carrierName ?? 'Unknown').' / '.($option->carrierService ?? 'Unknown')),
            trim(($option->fulfilmentCountry ?? 'Unknown').' / '.($option->labCode ?? 'Unknown')),
        ], $quote->options));

        return self::SUCCESS;
    }
}
