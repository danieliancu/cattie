<?php

namespace Tests\Feature\Catalogue;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_pages_render_consistent_cattie_content(): void
    {
        $pages = [
            'information.terms' => ['Terms and Conditions', 'Changes and cancellations'],
            'information.faq' => ['FAQ', 'Can I return a personalised item?'],
            'information.delivery' => ['Delivery &amp; Shipping', 'Production'],
            'information.returns' => ['Returns Policy', 'Personalised Products'],
            'information.privacy' => ['Privacy Policy', 'Photographs and AI artwork'],
            'information.payments' => ['Payment Methods', 'Available payment methods'],
        ];

        foreach ($pages as $route => [$title, $heading]) {
            $this->get(route($route))->assertOk()->assertSee($title, false)->assertSee($heading)
                ->assertSee('support@cattie.uk')->assertDontSee('Callie')->assertDontSee('TreatPod')
                ->assertDontSee('support-uk@callie.com')->assertDontSee('99 Day');
        }
    }

    public function test_delivery_policy_has_production_time_and_accessible_uk_estimates(): void
    {
        $this->get(route('information.delivery'))->assertOk()
            ->assertSee('3–5 working days')
            ->assertSee('Delivery method')->assertSee('After dispatch')->assertSee('Estimated total')
            ->assertSee('Royal Mail Tracked 48')->assertSee('2–4 working days')->assertSee('approx. 5–9 working days')
            ->assertSee('Royal Mail Tracked 24')->assertSee('1–2 working days')->assertSee('approx. 4–7 working days')
            ->assertSee('DPD')->assertSee('1 working day')->assertSee('approx. 4–6 working days')
            ->assertSee('Times are estimates and may occasionally vary.')
            ->assertSee('<table', false)->assertSee('<caption class="sr-only">', false)
            ->assertDontSee('1–3 working days')->assertDontSee('fulfilment provider');
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
            ->assertSee('Made')->assertSee('For You')->assertSee('Personalised Item Returns')
            ->assertSee('cannot normally accept returns or exchanges if you simply change your mind')
            ->assertSee('damaged, defective, incorrect, or is not as described')
            ->assertSee('This does not affect your statutory rights.')
            ->assertSee('href="'.route('information.returns').'"', false)
            ->assertSee('3–5 working days')->assertSee('Royal Mail Tracked 48')
            ->assertDontSee('99 Day')->assertDontSee('Delivery Time = Processing Time');
    }

    public function test_footer_links_to_every_information_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['information.terms', 'information.faq', 'information.delivery', 'information.returns', 'information.privacy', 'information.payments'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }
}
