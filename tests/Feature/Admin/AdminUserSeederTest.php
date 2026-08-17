<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_an_account_that_can_reach_the_panel(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::sole();

        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->actingAs($user)->get('/admin')->assertSuccessful();
    }

    public function test_it_is_idempotent_and_never_resets_an_existing_password(): void
    {
        $this->seed(AdminUserSeeder::class);

        $email = User::sole()->email;
        User::sole()->update(['password' => 'changed-by-the-admin']);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertTrue(Hash::check('changed-by-the-admin', User::sole()->password));
    }

    public function test_it_restores_admin_access_that_was_revoked(): void
    {
        $this->seed(AdminUserSeeder::class);
        User::sole()->update(['is_admin' => false]);

        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(User::sole()->is_admin);
    }
}
