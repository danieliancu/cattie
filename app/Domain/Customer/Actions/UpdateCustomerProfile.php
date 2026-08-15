<?php

namespace App\Domain\Customer\Actions;

use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\UkPostcode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateCustomerProfile
{
    private const ADDRESS_KEYS = ['address_line_1', 'address_line_2', 'city', 'county', 'postcode'];

    /**
     * Partial, key-aware update: a key absent from $data is left completely untouched
     * (existing value survives), a key present with a null/empty value explicitly
     * clears that field. Never touches User columns other than email.
     */
    public function handle(User $user, array $data): CustomerProfile
    {
        return DB::transaction(function () use ($user, $data) {
            if (array_key_exists('email', $data)) {
                $user->forceFill(['email' => $data['email']])->save();
            }

            $profile = CustomerProfile::query()->firstOrNew(['user_id' => $user->id]);

            foreach (['first_name', 'last_name', 'phone'] as $key) {
                if (array_key_exists($key, $data)) {
                    $profile->{$key} = $data[$key] !== null && $data[$key] !== '' ? Str::squish($data[$key]) : null;
                }
            }

            if (array_intersect(self::ADDRESS_KEYS, array_keys($data)) !== []) {
                $address = $profile->default_shipping_address ?? ['country' => 'GB'];

                foreach (self::ADDRESS_KEYS as $key) {
                    if (! array_key_exists($key, $data)) {
                        continue;
                    }

                    $value = $data[$key] !== null && $data[$key] !== '' ? Str::squish($data[$key]) : null;
                    $address[$key] = ($key === 'postcode' && $value !== null)
                        ? UkPostcode::format(UkPostcode::normalizeForInput($value))
                        : $value;
                }

                $address['country'] = 'GB';
                $profile->default_shipping_address = $address;
            }

            $profile->save();

            return $profile;
        });
    }
}
