<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'CAT-'.now()->format('ym').'-'.strtoupper($this->faker->bothify('??####')),
            'user_id' => null,
            'access_token_hash' => hash('sha256', $this->faker->uuid()),
            'email' => $this->faker->safeEmail(),
            'status' => OrderStatus::Paid,
            'currency' => 'GBP',
            'subtotal_minor' => 1950,
            'discount_minor' => 0,
            'shipping_minor' => 350,
            'tax_minor' => 0,
            'total_minor' => 2300,
            'shipping_status' => 'resolved',
            'tax_status' => 'resolved',
            'totals_status' => 'resolved',
            'is_payable' => false,
            'shipping_address' => [
                'first_name' => 'Mia',
                'last_name' => 'Smith',
                'address_line_1' => '1 High Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'GB',
            ],
            'placed_at' => now(),
        ];
    }
}
