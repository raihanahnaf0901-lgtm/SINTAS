<?php

namespace App\Services;

use App\Models\PembayaranSekolah;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransPayment
{
    public function ensureConfigured(): void
    {
        if (! is_string(config('school-subscriptions.server_key')) || trim(config('school-subscriptions.server_key')) === '') {
            throw ValidationException::withMessages(['payment' => 'Pembayaran belum dikonfigurasi. Hubungi pengelola SINTAS.']);
        }
    }

    public function createTransaction(PembayaranSekolah $payment, string $email, string $name): Response
    {
        return $this->request()->post($this->checkoutOrigin().'/snap/v1/transactions', [
            'transaction_details' => ['order_id' => $payment->order_id, 'gross_amount' => $payment->amount],
            'item_details' => [[
                'id' => $payment->plan_code, 'name' => mb_substr($payment->plan_name, 0, 50),
                'price' => $payment->amount, 'quantity' => 1,
            ]],
            'customer_details' => ['first_name' => mb_substr($name, 0, 100), 'email' => $email],
            'credit_card' => ['secure' => true],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function status(string $orderId): ?array
    {
        $origin = config('school-subscriptions.sandbox') ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';

        try {
            $response = $this->request()->get($origin.'/v2/'.rawurlencode($orderId).'/status');
        } catch (ConnectionException) {
            abort(503, 'Status pembayaran belum dapat diperiksa. Coba sinkronisasi lagi nanti.');
        }

        if ($response->notFound() || ($response->successful() && (string) $response->json('status_code') === '404')) {
            return null;
        }

        abort_unless($response->successful() && is_array($response->json()), 503, 'Layanan pembayaran belum dapat dihubungi. Coba lagi nanti.');

        return $response->json();
    }

    /** @param array<string, mixed> $payload */
    public function verifySignature(array $payload): bool
    {
        $this->ensureConfigured();

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return false;
            }
        }

        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].config('school-subscriptions.server_key'));

        return hash_equals($expected, $payload['signature_key']);
    }

    public function validCheckoutUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === parse_url($this->checkoutOrigin(), PHP_URL_HOST)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port'])
            && str_starts_with($parts['path'] ?? '', '/snap/');
    }

    private function checkoutOrigin(): string
    {
        return config('school-subscriptions.sandbox') ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';
    }

    private function request(): PendingRequest
    {
        $this->ensureConfigured();

        return Http::acceptJson()->asJson()
            ->withBasicAuth(config('school-subscriptions.server_key'), '')
            ->connectTimeout(3)->timeout(10);
    }
}
