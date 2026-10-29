<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Integration tests: website + REST API + the call to the Payment API (faked over HTTP). */
class HotelFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'hotel.api_key' => 'test-key',
            'hotel.payment_url' => 'http://payment.test/api.php/charges',
            'hotel.payment_key' => 'pay-test',
        ]);
        $this->seed();
    }

    private function api(): static
    {
        return $this->withHeaders(['X-API-Key' => 'test-key', 'Accept' => 'application/json']);
    }

    private function booking(array $over = []): array
    {
        return $over + [
            'room_id' => 'R101', 'guest_name' => 'Juan Dela Cruz', 'guest_email' => 'juan@example.com',
            'check_in' => now()->addDays(2)->format('Y-m-d'), 'check_out' => now()->addDays(4)->format('Y-m-d'), 'guests' => 2,
        ];
    }

    private function reserve(array $over = [])
    {
        return $this->api()->postJson('/api/reservations', $this->booking($over));
    }

    private function cardPayment(string $id, float $amount): array
    {
        return ['reservation_id' => $id, 'amount' => $amount, 'method' => 'card', 'bank' => 'BDO Unibank',
                'card_holder' => 'Juan Dela Cruz', 'card_number' => '4242424242424242', 'expiry' => '12/30', 'cvv' => '123'];
    }

    public function test_website_pages_load(): void
    {
        $this->get('/')->assertOk()->assertSee('Casa Grande');
        $this->get('/?check_in=' . now()->addDay()->format('Y-m-d') . '&check_out=' . now()->addDays(3)->format('Y-m-d') . '&guests=2')->assertOk();
        $this->get('/reservations')->assertOk();
        $this->get('/log')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_api_requires_the_key(): void
    {
        $this->getJson('/api/rooms')->assertStatus(401)->assertJsonPath('error', 'Invalid or missing API key');
        $this->api()->getJson('/api/rooms')->assertOk()->assertJsonCount(4);
    }

    public function test_valid_reservation_is_created(): void
    {
        $r = $this->reserve()->assertStatus(201)->assertJsonPath('status', 'PENDING')->assertJsonPath('nights', 2);
        $this->assertEquals(5000, $r->json('total_amount'));
        $this->assertSame('RSV-1001', $r->json('reservation_id'));
    }

    public function test_missing_field_is_rejected(): void
    {
        $this->reserve(['guest_name' => ''])->assertStatus(400)->assertJsonStructure(['error']);
    }

    public function test_guests_over_capacity_is_rejected(): void
    {
        $this->reserve(['guests' => 5])->assertStatus(400);
    }

    public function test_double_booking_is_rejected(): void
    {
        $this->reserve()->assertStatus(201);
        $this->reserve()->assertStatus(409);
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->api()->getJson('/api/reservations/RSV-9999')->assertStatus(404);
    }

    public function test_successful_card_payment_confirms_the_reservation(): void
    {
        Http::fake(['*' => Http::response(['transaction_id' => 'TXN-1', 'status' => 'APPROVED', 'details' => 'BDO Unibank Visa **** 4242'], 201)]);
        $res = $this->reserve()->json();

        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(201)->assertJsonPath('status', 'PAID');
        $this->api()->getJson('/api/reservations/' . $res['reservation_id'])->assertJsonPath('status', 'CONFIRMED');

        Http::assertSent(fn ($req) => $req->url() === 'http://payment.test/api.php/charges'
            && $req->hasHeader('X-API-Key', 'pay-test') && $req['method'] === 'card');
        // the on-screen log must never contain the full card number
        $this->assertStringNotContainsString('4242424242424242', json_encode(ApiLog::first()->request));
    }

    public function test_declined_card_keeps_the_reservation_pending(): void
    {
        Http::fake(['*' => Http::response(['status' => 'DECLINED', 'message' => 'Card declined by the issuing bank'], 402)]);
        $res = $this->reserve()->json();

        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(402)->assertJsonPath('status', 'FAILED');
        $this->api()->getJson('/api/reservations/' . $res['reservation_id'])->assertJsonPath('status', 'PENDING');
    }

    public function test_wrong_amount_is_rejected(): void
    {
        Http::fake();
        $res = $this->reserve()->json();
        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], 1))->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_payment_service_outage_returns_502(): void
    {
        Http::fake(function () { throw new ConnectionException('down'); });
        $res = $this->reserve()->json();
        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(502)->assertJsonPath('error', 'Payment service is unavailable');
    }

    public function test_reservation_can_be_cancelled(): void
    {
        $res = $this->reserve()->json();
        $this->api()->deleteJson('/api/reservations/' . $res['reservation_id'])->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    public function test_website_booking_flow_with_gcash(): void
    {
        Http::fake(['*' => Http::response(['transaction_id' => 'TXN-2', 'status' => 'APPROVED', 'details' => 'GCash 0917***4567'], 201)]);

        $this->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->get('/pay/RSV-1001')->assertOk()->assertSee('How would you like to pay?');
        $this->post('/pay/RSV-1001', ['method' => 'gcash', 'mobile_number' => '+639171234567'])->assertRedirect('/done/RSV-1001');
        Http::assertSent(fn ($req) => $req['mobile_number'] === '09171234567');
        $this->get('/done/RSV-1001')->assertOk()->assertSee('Your booking is confirmed');
    }
}
