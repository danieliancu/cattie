<?php

namespace App\Integrations\Prodigi;

use App\Integrations\Prodigi\Exceptions\ProdigiAuthenticationException;
use App\Integrations\Prodigi\Exceptions\ProdigiConfigurationException;
use App\Integrations\Prodigi\Exceptions\ProdigiNetworkException;
use App\Integrations\Prodigi\Exceptions\ProdigiNotFoundException;
use App\Integrations\Prodigi\Exceptions\ProdigiResponseException;
use App\Integrations\Prodigi\Exceptions\ProdigiValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProdigiClient
{
    /** @return array<string, mixed> */
    public function get(string $path, array $context = []): array
    {
        return $this->request('GET', $path, [], $context);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function post(string $path, array $payload, array $context = []): array
    {
        return $this->request('POST', $path, $payload, $context);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, array $payload, array $context): array
    {
        $apiKey = config('prodigi.api_key');
        $baseUrl = rtrim((string) config('prodigi.base_url'), '/');
        if (! is_string($apiKey) || trim($apiKey) === '' || $baseUrl === '') {
            throw new ProdigiConfigurationException('Prodigi credentials are not configured.', 'configuration');
        }

        try {
            $request = Http::baseUrl($baseUrl)->withHeaders(['X-API-Key' => $apiKey])->acceptJson()->asJson()->timeout((int) config('prodigi.timeout', 30));
            $response = $method === 'GET' ? $request->get($path) : $request->post($path, $payload);
        } catch (ConnectionException) {
            Log::warning('Prodigi network request failed.', $this->safeContext($method, $path, $context));
            throw new ProdigiNetworkException('Prodigi could not be reached before the timeout.', 'network');
        }

        $body = $response->json();
        $message = is_array($body) ? $this->providerMessage($body) : 'Prodigi returned an unexpected response.';
        $code = is_array($body) ? $this->providerCode($body) : null;

        if ($response->failed()) {
            Log::warning('Prodigi request failed.', $this->safeContext($method, $path, $context) + ['status' => $response->status(), 'provider_code' => $code, 'provider_message' => $message]);
            throw match ($response->status()) {
                401, 403 => new ProdigiAuthenticationException($message, 'authentication', $response->status(), $code),
                404 => new ProdigiNotFoundException($message, 'not_found', 404, $code),
                400, 409, 422 => new ProdigiValidationException($message, 'validation', $response->status(), $code),
                default => new ProdigiResponseException($message, $response->serverError() ? 'server' : 'client', $response->status(), $code),
            };
        }

        if (! is_array($body)) {
            throw new ProdigiResponseException('Prodigi returned malformed JSON.', 'malformed', $response->status());
        }

        return $body;
    }

    private function safeContext(string $method, string $path, array $context): array
    {
        return array_filter(['operation' => $method.' '.$path, 'sku' => $context['sku'] ?? null]);
    }

    private function providerMessage(array $body): string
    {
        $message = data_get($body, 'issues.0.description') ?? data_get($body, 'issues.0.message') ?? data_get($body, 'message') ?? data_get($body, 'outcome');

        return is_string($message) && $message !== '' ? $message : 'Prodigi request failed.';
    }

    private function providerCode(array $body): ?string
    {
        $code = data_get($body, 'issues.0.code') ?? data_get($body, 'code');

        return is_scalar($code) ? (string) $code : null;
    }
}
