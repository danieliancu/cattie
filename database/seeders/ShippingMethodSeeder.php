<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'royal_mail_48_tracked', 'name' => 'Royal Mail 48 Tracked', 'provider_service_code' => 'ROYAL_MAIL_48_TRACKED', 'price_minor' => 350, 'estimated_business_days_min' => 5, 'estimated_business_days_max' => 8, 'sort_order' => 10],
            ['code' => 'royal_mail_24_tracked', 'name' => 'Royal Mail 24 Tracked', 'provider_service_code' => 'ROYAL_MAIL_24_TRACKED', 'price_minor' => 413, 'estimated_business_days_min' => 4, 'estimated_business_days_max' => 7, 'sort_order' => 20],
            ['code' => 'dpd', 'name' => 'DPD', 'provider_service_code' => 'DPD', 'price_minor' => 749, 'estimated_business_days_min' => 4, 'estimated_business_days_max' => 4, 'sort_order' => 30],
        ];

        foreach ($methods as $method) {
            ShippingMethod::withTrashed()->updateOrCreate(
                ['provider' => 'treatpod', 'code' => $method['code']],
                [...$method, 'currency' => 'GBP', 'country' => 'GB', 'is_active' => true, 'deleted_at' => null],
            );
        }
    }
}
