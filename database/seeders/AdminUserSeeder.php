<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Guarantees a working admin login after a `migrate:fresh --seed`.
 *
 * The panel is gated on `is_admin`, so an empty users table locks the admin out
 * entirely with no way back in short of tinker. Seeding the account keeps a
 * database rebuild from being a lockout.
 *
 * Credentials come from the environment. `env()` is read here rather than
 * `config()` on purpose: this is a CLI-only, developer-facing seeder and there
 * is no runtime config to cache. If configuration *is* cached the environment is
 * not loaded and these fall back to the local defaults — which is why anything
 * other than a local environment refuses to seed a default password at all.
 */
class AdminUserSeeder extends Seeder
{
    private const DEFAULT_EMAIL = 'dani.iancu@yahoo.com';

    private const DEFAULT_NAME = 'Daniel Iancu';

    private const DEFAULT_PASSWORD = 'parola123';

    public function run(): void
    {
        $email = env('ADMIN_EMAIL', self::DEFAULT_EMAIL);
        $password = env('ADMIN_PASSWORD');

        if ($password === null) {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD must be set to seed the admin user outside local development.'
                );
            }

            $password = self::DEFAULT_PASSWORD;
        }

        $user = User::firstOrNew(['email' => $email]);

        // The password is only ever written on creation. Re-seeding an existing
        // database must not silently reset a password the admin has changed.
        if (! $user->exists) {
            $user->password = $password;
        }

        $user->name = env('ADMIN_NAME', self::DEFAULT_NAME);
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();
    }
}
