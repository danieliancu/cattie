<?php

namespace App\Http\Requests;

use App\Support\UkPostcode;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value;
        $this->merge([
            'first_name' => $clean($this->first_name), 'last_name' => $clean($this->last_name),
            'email' => strtolower((string) $clean($this->email)), 'phone' => $clean($this->phone),
            'address_line_1' => $clean($this->address_line_1), 'address_line_2' => $clean($this->address_line_2),
            'city' => $clean($this->city), 'county' => $clean($this->county),
            'postcode' => UkPostcode::normalizeForInput($this->postcode), 'country' => 'GB',
        ]);
    }

    public function rules(): array
    {
        return [
            'pricing_hash' => ['required', 'string', 'size:64'], 'checkout_idempotency_key' => ['required', 'uuid'],
            'shipping_method_id' => ['required', 'string', 'exists:shipping_methods,id'],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:150'], 'address_line_2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'], 'county' => ['nullable', 'string', 'max:100'],
            'postcode' => ['required', 'regex:'.UkPostcode::FORMAT_REGEX], 'country' => ['required', 'in:GB'],
            'save_default_address' => ['sometimes', 'boolean'],
        ];
    }

    public function customer(): array
    {
        $data = $this->validated();

        return ['email' => $data['email'], 'phone' => $data['phone'] ?? null, 'shipping_address' => [
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'address_line_1' => $data['address_line_1'], 'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'], 'county' => $data['county'] ?? null,
            'postcode' => UkPostcode::format($data['postcode']), 'country' => 'GB',
        ]];
    }
}
