<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword1'), 'is_admin' => false]);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'oldpassword1',
            'password' => 'newpassword2',
            'password_confirmation' => 'newpassword2',
        ])->assertRedirect()->assertSessionHas('password_status');

        $this->assertTrue(Hash::check('newpassword2', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword1'), 'is_admin' => false]);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'not-it',
            'password' => 'newpassword2',
            'password_confirmation' => 'newpassword2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('oldpassword1', $user->fresh()->password));
    }

    public function test_google_only_account_can_set_a_password_without_a_current_one(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'g-1', 'is_admin' => false]);

        $this->actingAs($user)->put(route('account.password.update'), [
            'password' => 'brandnewpass3',
            'password_confirmation' => 'brandnewpass3',
        ])->assertRedirect()->assertSessionHas('password_status');

        $this->assertTrue(Hash::check('brandnewpass3', $user->fresh()->password));
    }

    public function test_customer_can_delete_account_and_orders_are_kept_but_detached(): void
    {
        $user = User::factory()->create(['password' => Hash::make('mypassword1'), 'is_admin' => false]);
        CustomerProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Mia']);
        $order = $this->order($user);

        $this->actingAs($user)->delete(route('account.destroy'), ['confirm_password' => 'mypassword1'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull(User::query()->find($user->id));
        $this->assertNull(CustomerProfile::query()->where('user_id', $user->id)->first());
        $this->assertNotNull($order->fresh());
        $this->assertNull($order->fresh()->user_id);
    }

    public function test_account_deletion_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('mypassword1'), 'is_admin' => false]);

        $this->actingAs($user)->delete(route('account.destroy'), ['confirm_password' => 'wrong'])
            ->assertSessionHasErrors('confirm_password');

        $this->assertNotNull(User::query()->find($user->id));
        $this->assertAuthenticatedAs($user);
    }

    private function order(User $user): Order
    {
        return Order::query()->create([
            'number' => 'CAT-SEC', 'user_id' => $user->id, 'access_token_hash' => hash('sha256', 'token'),
            'email' => $user->email, 'status' => OrderStatus::Paid, 'currency' => 'GBP',
            'subtotal_minor' => 1950, 'discount_minor' => 0, 'shipping_minor' => 350, 'tax_minor' => 0, 'total_minor' => 2300,
            'shipping_status' => 'resolved', 'tax_status' => 'resolved', 'totals_status' => 'resolved', 'is_payable' => false,
            'shipping_address' => ['first_name' => 'Mia', 'last_name' => 'Smith', 'address_line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'SW1A 1AA', 'country' => 'GB'],
            'placed_at' => now(),
        ]);
    }
}
