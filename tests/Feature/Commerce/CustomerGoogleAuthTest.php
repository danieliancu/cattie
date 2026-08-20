<?php

namespace Tests\Feature\Commerce;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class CustomerGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Gina Google'): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'avatar' => 'https://example.com/avatar.png',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_callback_creates_a_verified_customer_and_signs_them_in(): void
    {
        $this->fakeGoogleUser('google-123', 'NEW@example.com', 'Gina Google');

        $this->get(route('auth.google.callback'))->assertRedirect(route('account.index'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Gina Google', $user->name);
        $this->assertNull($user->password);
        $this->assertFalse($user->is_admin);
    }

    public function test_google_callback_links_an_existing_customer_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'known@example.com', 'is_admin' => false, 'google_id' => null]);
        $this->fakeGoogleUser('google-999', 'known@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('account.index'));

        $existing->refresh();
        $this->assertSame('google-999', $existing->google_id);
        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->where('email', 'known@example.com')->count());
    }

    public function test_google_login_is_refused_for_admin_accounts(): void
    {
        User::factory()->create(['email' => 'boss@example.com', 'is_admin' => true, 'google_id' => null]);
        $this->fakeGoogleUser('google-admin', 'boss@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
