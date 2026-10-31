<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\ApiLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Integration point: Hotel Reservation System -> Payment API (REST, JSON, API key header). */
class PaymentGateway
{
    /** @return array{0:int,1:array} [HTTP status, decoded JSON] */
    public function charge(array $payload): array
    {
        $url = config('hotel.payment_url');
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'X-API-Key' => config('hotel.payment_key')];
        try {
            $res = Http::withHeaders($headers)->timeout(10)->asJson()->post($url, $payload);
        } catch (ConnectionException $e) {
            $this->log($url, $headers, $payload, 0, ['error' => 'Connection failed: ' . $e->getMessage()]);
            throw new BookingException('Payment service is unavailable', 502);
        }
        $data = $res->json() ?? ['raw' => mb_substr(strip_tags($res->body()), 0, 200)];
        $this->log($url, $headers, $payload, $res->status(), $data);
        return [$res->status(), $data];
    }

    /** Demo integration seam for simulated wallet payments. Replace this method with a provider SDK call. */
    public function simulateWallet(array $payload): array
    {
        $url = 'demo://payment-simulator';
        $headers = ['X-API-Key' => config('hotel.payment_key'), 'Content-Type' => 'application/json'];
        $response = [
            'transaction_id' => 'DEMO-' . strtoupper(substr(str_replace('-', '', $payload['session_id']), 0, 12)),
            'status' => 'APPROVED',
            'details' => ucfirst($payload['method']) . ' demo payment',
        ];
        $this->log($url, $headers, $payload, 201, $response);
        return [201, $response];
    }

    /** Simulate a card charge locally without depending on the separate Payment API process. */
    public function simulateCard(array $payload): array
    {
        $url = 'demo://payment-simulator';
        $headers = ['X-API-Key' => config('hotel.payment_key'), 'Content-Type' => 'application/json'];
        $cardNumber = preg_replace('/\D/', '', (string) $payload['card_number']);
        $brand = match (true) {
            preg_match('/^4/', $cardNumber) === 1 => 'Visa',
            preg_match('/^(5[1-5]\d{2}|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/', $cardNumber) === 1 => 'Mastercard',
            preg_match('/^35(2[89]|[3-8]\d)/', $cardNumber) === 1 => 'JCB',
            preg_match('/^3[47]/', $cardNumber) === 1 => 'Amex',
            default => 'Card',
        };
        $details = trim($payload['bank']) . ' ' . $brand . ' **** ' . substr($cardNumber, -4);
        $approved = $cardNumber !== '4000000000000002';
        $response = [
            'transaction_id' => 'DEMO-' . strtoupper(bin2hex(random_bytes(4))),
            'status' => $approved ? 'APPROVED' : 'DECLINED',
            'details' => $details,
            'message' => $approved ? 'Demo payment approved' : 'Demo card declined',
        ];
        $this->log($url, $headers, $payload, $approved ? 201 : 402, $response);

        return [$approved ? 201 : 402, $response];
    }

    private function log(string $url, array $headers, array $payload, int $status, array $response): void
    {
        if (isset($payload['card_number'])) $payload['card_number'] = '**** **** **** ' . substr($payload['card_number'], -4);
        if (isset($payload['cvv'])) $payload['cvv'] = '***';
        if (isset($headers['X-API-Key'])) $headers['X-API-Key'] = '***';
        ApiLog::create(['method' => 'POST', 'url' => $url, 'headers' => $headers, 'request' => $payload, 'status' => $status, 'response' => $response]);
    }
}
