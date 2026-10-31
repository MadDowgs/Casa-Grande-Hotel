<?php

namespace App\Services;

use App\Exceptions\BrevoEmailException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoEmailService
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function send(string $recipientEmail, string $recipientName, string $subject, string $htmlContent): void
    {
        $apiKey = (string) config('services.brevo.api_key');
        $senderEmail = (string) config('services.brevo.sender_email');
        $senderName = (string) config('services.brevo.sender_name');

        if ($apiKey === '' || $senderEmail === '') {
            $missing = array_filter([
                'BREVO_API_KEY' => $apiKey === '',
                'BREVO_SENDER_EMAIL' => $senderEmail === '',
            ]);
            $missingSettings = array_keys($missing);

            Log::error('Brevo email configuration is incomplete.', ['missing_settings' => $missingSettings]);

            throw new BrevoEmailException('Brevo email configuration is incomplete.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['api-key' => $apiKey])
                ->timeout(10)
                ->post(self::ENDPOINT, [
                    'sender' => ['name' => $senderName, 'email' => $senderEmail],
                    'to' => [['email' => $recipientEmail, 'name' => $recipientName]],
                    'subject' => $subject,
                    'htmlContent' => $htmlContent,
                ]);
        } catch (ConnectionException $exception) {
            Log::error('Brevo transactional email request could not connect.', [
                'exception' => $exception::class,
                'error' => $this->redact($exception->getMessage(), $apiKey, $senderEmail, $recipientEmail),
            ]);

            throw new BrevoEmailException('Could not connect to Brevo.', 0, $exception);
        }

        if (! $response->successful()) {
            $this->logRejectedResponse($response, $apiKey, $senderEmail, $recipientEmail);

            throw new BrevoEmailException("Brevo rejected the transactional email (HTTP {$response->status()}).");
        }
    }

    private function logRejectedResponse(Response $response, string $apiKey, string $senderEmail, string $recipientEmail): void
    {
        $body = $response->json();
        $providerMessage = is_array($body) && is_string($body['message'] ?? null)
            ? $body['message']
            : null;

        if ($providerMessage !== null) {
            $providerMessage = $this->redact($providerMessage, $apiKey, $senderEmail, $recipientEmail);
        }

        Log::error('Brevo rejected a transactional email.', [
            'status' => $response->status(),
            'error_code' => is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : null,
            'provider_message' => $providerMessage,
        ]);
    }

    private function redact(string $message, string $apiKey, string $senderEmail, string $recipientEmail): string
    {
        $message = str_replace([$apiKey, $senderEmail, $recipientEmail], '[redacted]', $message);
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted email]', $message);

        return mb_substr($message, 0, 300);
    }
}
