<?php

namespace Tests\Feature;

use App\Exceptions\BrevoEmailException;
use App\Services\BrevoEmailService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BrevoEmailServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.brevo.api_key' => 'brevo-test-key',
            'services.brevo.sender_email' => 'hotel@example.com',
            'services.brevo.sender_name' => 'Casa Grande Hotel',
        ]);
    }

    public function test_it_sends_transactional_email_to_brevo(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo-message-1'], 201),
        ]);

        app(BrevoEmailService::class)->send(
            'guest@example.com',
            'Hotel Guest',
            'Booking confirmed - RSV-1001',
            '<h1>Your booking is confirmed</h1>',
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->method() === 'POST'
            && $request->hasHeader('api-key', 'brevo-test-key')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request['sender'] === ['name' => 'Casa Grande Hotel', 'email' => 'hotel@example.com']
            && $request['to'] === [['email' => 'guest@example.com', 'name' => 'Hotel Guest']]
            && $request['subject'] === 'Booking confirmed - RSV-1001'
            && $request['htmlContent'] === '<h1>Your booking is confirmed</h1>');
    }

    public function test_it_logs_and_throws_when_brevo_rejects_an_email(): void
    {
        Log::spy();
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'code' => 'invalid_parameter',
                'message' => 'Invalid recipient guest@example.com for key brevo-test-key',
            ], 400),
        ]);

        try {
            app(BrevoEmailService::class)->send('guest@example.com', 'Hotel Guest', 'Subject', '<p>Body</p>');
            $this->fail('A rejected Brevo request must throw.');
        } catch (BrevoEmailException $exception) {
            $this->assertSame('Brevo rejected the transactional email (HTTP 400).', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $message === 'Brevo rejected a transactional email.'
            && $context['status'] === 400
            && $context['error_code'] === 'invalid_parameter'
            && ! str_contains($context['provider_message'], 'guest@example.com')
            && ! str_contains($context['provider_message'], 'brevo-test-key'));
    }

    public function test_it_reports_an_invalid_brevo_api_key(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response([
                'code' => 'unauthorized',
                'message' => 'Invalid API key',
            ], 401),
        ]);

        try {
            app(BrevoEmailService::class)->send('guest@example.com', 'Hotel Guest', 'Subject', '<p>Body</p>');
            $this->fail('An invalid API key must be reported as a rejected email.');
        } catch (BrevoEmailException $exception) {
            $this->assertSame('Brevo rejected the transactional email (HTTP 401).', $exception->getMessage());
        }
    }

    public function test_it_logs_and_throws_when_brevo_connection_fails(): void
    {
        Log::spy();
        Http::fake(function () {
            throw new ConnectionException('Brevo is unavailable.');
        });

        try {
            app(BrevoEmailService::class)->send('guest@example.com', 'Hotel Guest', 'Subject', '<p>Body</p>');
            $this->fail('A connection failure must throw.');
        } catch (BrevoEmailException $exception) {
            $this->assertSame('Could not connect to Brevo.', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $message === 'Brevo transactional email request could not connect.'
            && $context['exception'] === ConnectionException::class
            && $context['error'] === 'Brevo is unavailable.');
    }
}
