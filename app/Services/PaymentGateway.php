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

    private function log(string $url, array $headers, array $payload, int $status, array $response): void
    {
        if (isset($payload['card_number'])) $payload['card_number'] = '**** **** **** ' . substr($payload['card_number'], -4);
        if (isset($payload['cvv'])) $payload['cvv'] = '***';
        ApiLog::create(['method' => 'POST', 'url' => $url, 'headers' => $headers, 'request' => $payload, 'status' => $status, 'response' => $response]);
    }
}
