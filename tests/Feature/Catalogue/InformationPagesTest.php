<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_pages_render_consistent_kattie_content(): void
    {
        $pages = [
            'information.terms' => ['Terms and Conditions', 'Changes and cancellations'],
            'information.faq' => ['FAQ', 'Can I return a personalised item?'],
            'information.delivery' => ['Delivery &amp; Shipping', 'Production'],
            'information.returns' => ['Returns Policy', 'Personalised Products'],
            'information.privacy' => ['Privacy Policy', 'Photographs and AI artwork'],
            'information.cookies' => ['Manage Cookies', 'Necessary cookies'],
            'information.payments' => ['Payment Methods', 'Available payment methods'],
        ];

        foreach ($pages as $route => [$title, $heading]) {
            $this->get(route($route))->assertOk()->assertSee($title, false)->assertSee($heading)
                ->assertSee('support@kattie.uk')->assertDontSee('Callie')->assertDontSee('TreatPod')
                ->assertDontSee('support-uk@callie.com')->assertDontSee('99 Day');
        }
    }

    public function test_delivery_policy_has_production_time_and_accessible_uk_estimates(): void
    {
        $this->get(route('information.delivery'))->assertOk()
            ->assertSee('Made and printed in the UK')
            ->assertSee('Your personalised gift is made to order, usually within 3–5 working days.')
            ->assertSee('Price')->assertSee('Estimated delivery')->assertSee('Delivery method')
            ->assertSee('£3.50')->assertSee('5–8 business days')->assertSee('Royal Mail 48 Tracked')
            ->assertSee('£4.13')->assertSee('4–7 business days')->assertSee('Royal Mail 24 Tracked')
            ->assertSee('£7.49')->assertSee('4 business days')->assertSee('DPD')
            ->assertSee('Delivery times are estimates and may occasionally vary during busy periods or due to carrier delays.')
            ->assertSee('<table', false)->assertSee('<caption class="sr-only">', false)
            ->assertDontSee('After dispatch')->assertDontSee('approx. 5–9 working days')
            ->assertDontSee('approx. 4–6 working days')->assertDontSee('Delivery estimates start after')
            ->assertDontSee('fulfilment provider')->assertDontSee('TreatPod');
    }

    public function test_returns_policy_covers_personalised_fault_and_cancellation_cases(): void
    {
        $this->get(route('information.returns'))->assertOk()
            ->assertSee('Personalised Products')->assertSee('simply change your mind')
            ->assertSee('Damaged or Defective Items')->assertSee('clear photographs')
            ->assertSee('Incorrect Items')->assertSee('does not match its description')
            ->assertSee('Refunds and Replacements')->assertSee('may offer an appropriate replacement or refund')
            ->assertSee('cannot guarantee that changes or cancellations will be possible once production has started')
            ->assertSee('How to Contact Us')->assertSee('Statutory Rights')
            ->assertSee('This does not affect your statutory rights.')
            ->assertDontSee('automatic refund')->assertDontSee('99 Day');
    }

    public function test_product_policy_is_concise_consistent_and_links_to_full_returns_policy(): void
    {
        $this->seed();
        $product = Product::query()->active()->where('slug', 'water-bottle-with-red-flip-lid')->firstOrFail();

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('Made')->assertSee('For You')
            ->assertSee('cannot normally accept returns or exchanges if you simply change your mind')
            ->assertSee('damaged, defective, incorrect, or is not as described')
            ->assertSee('This does not affect your statutory rights.')
            ->assertSee('href="'.route('information.returns').'"', false)
            ->assertSee('Made and printed in the UK')->assertSee('3–5 working days')
            ->assertSee('£3.50')->assertSee('Royal Mail 48 Tracked')->assertSee('5–8 business days')
            ->assertSee('£4.13')->assertSee('Royal Mail 24 Tracked')->assertSee('4–7 business days')
            ->assertSee('£7.49')->assertSee('DPD')->assertSee('4 business days')
            ->assertDontSee('After dispatch')->assertDontSee('approx. 5–9 working days')
            ->assertDontSee('99 Day')->assertDontSee('Delivery Time = Processing Time');
    }

    public function test_footer_links_to_every_information_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['information.terms', 'information.faq', 'information.delivery', 'information.returns', 'information.privacy', 'information.cookies', 'information.payments'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }
}
