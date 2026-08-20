<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_six_hero_product_tiles(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach ([
            'Water Bottle with Red Flip Lid',
            'Small Plastic Lunchbox',
            'Personalised Stationery &amp; Pencil Tin',
            'Custom A3 Wall Print',
            'Personalised School Backpack',
            'Personalised Pet Bowl',
        ] as $name) {
            $response->assertSee($name, false);
        }
    }
}
