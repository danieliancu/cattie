<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_my_details(): void
    {
        $this->get(route('account.details'))->assertRedirect(route('login'));
        $this->patch(route('account.details.update'), ['first_name' => 'Mia'])->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_empty_my_details_page_with_email_prefilled(): void
    {
        $user = User::factory()->create(['email' => 'mia@example.com', 'is_admin' => false]);

        $this->actingAs($user)->get(route('account.details'))->assertOk()
            ->assertSee('My Details')->assertSee('value="mia@example.com"', false)
            ->assertSee('value=""', false);
    }

    public function test_my_details_page_populates_from_existing_profile(): void
    {
        $user = User::factory()->create(['email' => 'mia@example.com', 'is_admin' => false]);
        $this->profile($user, ['first_name' => 'Mia', 'last_name' => 'Smith', 'phone' => '07700900000'], [
            'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA',
        ]);

        $this->actingAs($user)->get(route('account.details'))->assertOk()
            ->assertSee('value="Mia"', false)->assertSee('value="Smith"', false)
            ->assertSee('value="07700900000"', false)->assertSee('value="1 High Street"', false)
            ->assertSee('value="London"', false)->assertSee('value="SW1A 1AA"', false);
    }

    public function test_my_details_page_renders_shared_component_field_markup(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('account.details'))->assertOk()
            ->assertSee('autocomplete="given-name"', false)->assertSee('autocomplete="family-name"', false)
            ->assertSee('autocomplete="email"', false)->assertSee('autocomplete="tel"', false)
            ->assertSee('autocomplete="shipping address-line1"', false)->assertSee('autocomplete="shipping address-line2"', false)
            ->assertSee('autocomplete="shipping address-level2"', false)->assertSee('autocomplete="shipping address-level1"', false)
            ->assertSee('autocomplete="shipping postal-code"', false);
    }

    public function test_patch_details_creates_profile_on_first_partial_save(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['first_name' => 'Mia'])
            ->assertOk()->assertJson(['status' => 'saved']);

        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Mia', $profile->first_name);
        $this->assertNull($profile->last_name);
        $this->assertNull($profile->default_shipping_address);
    }

    public function test_patch_details_updates_single_field_without_requiring_full_address(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['city' => 'London'])
            ->assertOk()->assertJsonMissingValidationErrors(['postcode', 'address_line_1']);

        $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('London', $profile->default_shipping_address['city']);
    }

    public function test_patch_details_merges_address_fields_without_clobbering_previously_saved_ones(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['address_line_1' => '1 High Street'])->assertOk();
        $this->actingAs($user)->patchJson(route('account.details.update'), ['city' => 'London'])->assertOk();

        $address = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail()->default_shipping_address;
        $this->assertSame('1 High Street', $address['address_line_1']);
        $this->assertSame('London', $address['city']);
    }

    public function test_patch_details_is_scoped_to_the_authenticated_users_own_profile(): void
    {
        $userA = User::factory()->create(['is_admin' => false]);
        $userB = User::factory()->create(['is_admin' => false]);

        $this->actingAs($userA)->patchJson(route('account.details.update'), ['first_name' => 'Alice'])->assertOk();
        $this->actingAs($userB)->patchJson(route('account.details.update'), ['first_name' => 'Bob'])->assertOk();

        $this->assertSame('Alice', CustomerProfile::query()->where('user_id', $userA->id)->firstOrFail()->first_name);
        $this->assertSame('Bob', CustomerProfile::query()->where('user_id', $userB->id)->firstOrFail()->first_name);
        $this->assertSame(2, CustomerProfile::query()->count());
    }

    public function test_customer_profile_default_shipping_address_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);

        $raw = DB::table('customer_profiles')->where('user_id', $user->id)->value('default_shipping_address');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('London', $raw);
        $this->assertStringNotContainsString('High Street', $raw);
    }

    public function test_customer_profile_hides_default_shipping_address_from_array_and_json_serialization(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $profile = $this->profile($user, [], ['address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA']);

        $this->assertArrayNotHasKey('default_shipping_address', $profile->toArray());
        $this->assertStringNotContainsString('High Street', $profile->toJson());
    }

    public function test_patch_details_allows_county_to_stay_empty(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), [
            'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA',
        ])->assertOk()->assertJsonMissingValidationErrors('county');

        $address = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail()->default_shipping_address;
        $this->assertNull($address['county'] ?? null);
    }

    public function test_patch_details_updates_user_email_lowercased_and_trimmed(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['email' => '  MIA@EXAMPLE.COM '])->assertOk();

        $this->assertSame('mia@example.com', $user->fresh()->email);
    }

    public function test_patch_details_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'is_admin' => false]);
        $user = User::factory()->create(['email' => 'mine@example.com', 'is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['email' => 'taken@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame('mine@example.com', $user->fresh()->email);
    }

    public function test_patch_details_email_change_does_not_alter_past_order_email_snapshot(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'is_admin' => false]);
        $order = $this->order($user, 'old@example.com');

        $this->actingAs($user)->patchJson(route('account.details.update'), ['email' => 'new@example.com'])->assertOk();

        $this->assertSame('old@example.com', $order->fresh()->email);
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_patch_details_email_change_does_not_log_user_out(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['email' => 'new@example.com'])->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_my_details_partial_update_does_not_require_fields_that_checkout_requires(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->patchJson(route('account.details.update'), ['phone' => '07700900000'])
            ->assertOk()->assertJsonMissingValidationErrors(['postcode', 'city', 'address_line_1']);
    }

    private function profile(User $user, array $attributes, array $address = []): CustomerProfile
    {
        return CustomerProfile::query()->create([
            'user_id' => $user->id,
            ...$attributes,
            'default_shipping_address' => $address === [] ? null : ['country' => 'GB', ...$address],
        ]);
    }

    private function order(User $user, string $email): Order
    {
        return Order::query()->create(['number' => 'CAT-'.fake()->unique()->numerify('#####'), 'user_id' => $user->id, 'access_token_hash' => hash('sha256', 'token'), 'email' => $email, 'status' => OrderStatus::Paid, 'currency' => 'GBP', 'subtotal_minor' => 1950, 'discount_minor' => 0, 'shipping_minor' => 350, 'tax_minor' => 0, 'total_minor' => 2300, 'shipping_status' => 'resolved', 'tax_status' => 'resolved', 'totals_status' => 'resolved', 'is_payable' => false, 'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'], 'placed_at' => now()]);
    }
}
