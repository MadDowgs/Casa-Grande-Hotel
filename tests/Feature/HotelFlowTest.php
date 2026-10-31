<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\BookingService;
use App\Exceptions\BookingException;
use App\Mail\BookingConfirmation;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        $this->get('/')->assertOk()->assertSee('Casa Grande')->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/?check_in='.now()->addDay()->format('Y-m-d').'&check_out='.now()->addDays(3)->format('Y-m-d').'&guests=2')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Log in')->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/register')->assertOk()->assertSee('Create an account')->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/reservations')->assertRedirect('/login');
        $this->get('/book/R101')->assertRedirect('/login');
        $this->get('/log')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_registration_returns_guest_to_the_room_they_wanted_to_book(): void
    {
        $this->get('/book/R101')->assertRedirect('/login');

        $this->post('/register', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'password' => 'secure-pass-123',
            'password_confirmation' => 'secure-pass-123',
        ])->assertRedirect('/book/R101');

        $this->assertAuthenticated();
        $this->get('/book/R101')->assertOk()->assertSee('guest@example.com');
        $this->get('/reservations')->assertOk()->assertSee('No reservations yet.');
    }

    public function test_reservations_are_scoped_to_the_signed_in_user(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)->get('/reservations')
            ->assertOk()->assertSee('No reservations yet.')->assertDontSee('Juan Dela Cruz');
        $this->get('/pay/RSV-1001')->assertNotFound();
        $this->post('/reservations/RSV-1001/cancel')->assertNotFound();

        $this->actingAs($owner)->get('/reservations')->assertOk()->assertSee('RSV-1001');
    }

    public function test_registered_users_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['email' => 'returning@example.com', 'password' => 'secure-pass-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secure-pass-123'])
            ->assertRedirect(route('rooms').'#rooms');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_area_is_hidden_and_restricted_to_administrators(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->get('/')->assertOk()->assertDontSee('/admin');

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secure-pass-123',
            'is_admin' => true,
        ]);
        $this->post('/logout');
        $this->get('/book/R101')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'secure-pass-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk()->assertSee('Dashboard')->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/admin/reservations')->assertOk()->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/admin/rooms')->assertOk()->assertSee('img/logo.png')->assertSee('rel="icon"', false);
        $this->get('/admin/users')->assertOk()->assertSee('img/logo.png')->assertSee('rel="icon"', false);
    }

    public function test_admin_can_manage_rooms_without_deleting_booking_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $this->post('/admin/rooms', [
            'id' => 'r505',
            'name' => 'Garden Room',
            'type' => 'Garden',
            'price_per_night' => 3200,
            'capacity' => 2,
            'inventory_capacity' => 5,
            'available' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R505', 'price_per_night' => 3200, 'available' => true]);

        $this->put('/admin/rooms/R505', [
            'name' => 'Garden Room Deluxe',
            'type' => 'Deluxe',
            'price_per_night' => 4000,
            'capacity' => 3,
            'inventory_capacity' => 5,
            'available' => '0',
        ])->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R505', 'price_per_night' => 4000, 'available' => false]);
        $this->get('/admin/rooms')->assertOk()->assertSee('Fully booked');
        $this->get('/book/R505')->assertOk()->assertSee('no bookable dates', false);

        $this->put('/admin/rooms/R505', [
            'name' => 'Garden Room Deluxe',
            'type' => 'Deluxe',
            'price_per_night' => 4000,
            'capacity' => 3,
            'inventory_capacity' => 5,
            'available' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R505', 'available' => true]);
        $this->get('/book/R505')->assertOk();

        $this->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->delete('/admin/rooms/R101')->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R101']);

        $this->delete('/admin/rooms/R505')->assertRedirect();
        $this->assertDatabaseMissing('rooms', ['id' => 'R505']);
    }

    public function test_admin_can_cancel_reservations_and_manage_account_roles_safely(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');

        $this->get('/admin/reservations')->assertOk()->assertSee('RSV-1001')->assertSee('Juan Dela Cruz');
        $this->post('/admin/reservations/1/cancel')->assertRedirect();
        $this->assertDatabaseHas('reservations', ['id' => 1, 'status' => 'CANCELLED']);

        $guest = User::factory()->create();
        $this->put("/admin/users/{$guest->id}/role", ['is_admin' => '1'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'is_admin' => true]);
        $this->put("/admin/users/{$guest->id}/role", ['is_admin' => '0'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'is_admin' => false]);

        $this->put("/admin/users/{$admin->id}/role", ['is_admin' => '0'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
    }

    public function test_admin_can_delete_reservations_and_their_payment_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');

        $reservation = Reservation::firstOrFail();
        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->total_amount,
            'method' => 'card',
            'status' => 'PAID',
            'transaction_id' => 'TXN-DELETE-1',
        ]);
        $paymentSession = PaymentSession::create([
            'reservation_id' => $reservation->id,
            'user_id' => $admin->id,
            'session_token' => 'delete-session-token',
            'provider' => 'gcash',
            'amount' => $reservation->total_amount,
            'status' => 'PAID',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->get('/admin/reservations')->assertOk()
            ->assertSee('Delete')
            ->assertSee('all its payment records');
        $this->delete("/admin/reservations/{$reservation->id}")
            ->assertRedirect()
            ->assertSessionHas('notice');

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('payment_sessions', ['id' => $paymentSession->id]);
    }

    public function test_admin_can_delete_accounts_but_keeps_reservation_history_and_last_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $secondAdmin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['name' => 'Guest To Delete']);

        $this->actingAs($guest)->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->actingAs($admin);

        $this->get('/admin/users')->assertOk()->assertSee('Delete account');
        $this->delete("/admin/users/{$admin->id}")->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);

        $this->delete("/admin/users/{$guest->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        $this->assertDatabaseHas('reservations', [
            'id' => 1,
            'user_id' => null,
            'guest_name' => 'Juan Dela Cruz',
            'status' => 'PENDING',
        ]);

        $this->delete("/admin/users/{$secondAdmin->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $secondAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
    }

    public function test_admin_bootstrap_command_promotes_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'new-admin@example.com']);

        $this->artisan('admin:promote', ['email' => $user->email])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => true]);
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
        Room::where('id', 'R101')->update(['inventory_capacity' => 1]);
        $this->reserve()->assertStatus(201);
        $this->reserve()->assertStatus(409);
    }

    public function test_unknown_reservation_returns_404(): void
    {
        $this->api()->getJson('/api/reservations/RSV-9999')->assertStatus(404);
    }

    public function test_successful_card_payment_confirms_the_reservation(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response(['transaction_id' => 'TXN-1', 'status' => 'APPROVED', 'details' => 'BDO Unibank Visa **** 4242'], 201)]);
        $res = $this->reserve()->json();

        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(201)->assertJsonPath('status', 'PAID');
        $this->api()->getJson('/api/reservations/'.$res['reservation_id'])->assertJsonPath('status', 'CONFIRMED');

        Http::assertSent(fn ($req) => $req->url() === 'http://payment.test/api.php/charges'
            && $req->hasHeader('X-API-Key', 'pay-test') && $req['method'] === 'card');
        // the on-screen log must never contain the full card number
        $this->assertStringNotContainsString('4242424242424242', json_encode(ApiLog::first()->request));
        $this->assertSame('***', ApiLog::first()->headers['X-API-Key']);
        Mail::assertSent(BookingConfirmation::class, fn (BookingConfirmation $mail) => $mail->hasTo('juan@example.com')
            && $mail->reservation->code === 'RSV-1001'
            && $mail->payment->method === 'card'
            && $mail->payment->details === 'BDO Unibank Visa **** 4242');
    }

    public function test_website_demo_card_accepts_an_unlisted_issuing_bank_and_confirms_booking(): void
    {
        Mail::fake();
        Http::fake(function () {
            throw new ConnectionException('Payment API is not running');
        });
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->get('/pay/RSV-1001')->assertOk()->assertDontSee('Demo only: no bank is contacted')->assertSee('bank-options');

        $this->post('/pay/RSV-1001', [
            'method' => 'card',
            'bank' => 'Community Credit Union',
            'card_holder' => 'Demo Guest',
            'card_number' => '4242 4242 4242 4242',
            'expiry' => '12/30',
            'cvv' => '123',
        ])->assertRedirect('/done/RSV-1001');

        $this->assertDatabaseHas('reservations', ['id' => 1, 'status' => 'CONFIRMED']);
        $this->assertDatabaseHas('payments', [
            'reservation_id' => 1,
            'status' => 'PAID',
            'details' => 'Community Credit Union Visa **** 4242',
        ]);
        $payment = ApiLog::first();
        $this->assertSame('demo://payment-simulator', $payment->url);
        $this->assertSame('**** **** **** 4242', $payment->request['card_number']);
        $this->assertSame('***', $payment->request['cvv']);
        Http::assertNothingSent();
        $this->assertSame('***', $payment->headers['X-API-Key']);
        Mail::assertSent(BookingConfirmation::class, fn (BookingConfirmation $mail) => $mail->hasTo('juan@example.com')
            && $mail->reservation->room->name === 'Standard Queen'
            && $mail->payment->method === 'card');
        $emailHtml = Mail::sent(BookingConfirmation::class)->first()->render();
        $this->assertStringContainsString('Standard Queen', $emailHtml);
        $this->assertStringContainsString('Check-in', $emailHtml);
        $this->assertStringContainsString('Community Credit Union Visa **** 4242', $emailHtml);
    }

    public function test_website_demo_card_rejects_invalid_card_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reserve', $this->booking());

        $this->from('/pay/RSV-1001')->post('/pay/RSV-1001', [
            'method' => 'card',
            'bank' => 'BDO Unibank',
            'card_holder' => 'Demo Guest',
            'card_number' => '1234567890123456',
            'expiry' => '12/30',
            'cvv' => '123',
        ])->assertRedirect('/pay/RSV-1001')->assertSessionHas('error');

        $this->assertDatabaseHas('reservations', ['id' => 1, 'status' => 'PENDING']);
        $this->assertDatabaseMissing('payments', ['reservation_id' => 1]);
        $this->assertDatabaseMissing('api_logs', ['url' => 'demo://payment-simulator']);
    }

    public function test_declined_card_keeps_the_reservation_pending(): void
    {
        Http::fake(['*' => Http::response(['status' => 'DECLINED', 'message' => 'Card declined by the issuing bank'], 402)]);
        $res = $this->reserve()->json();

        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(402)->assertJsonPath('status', 'FAILED');
        $this->api()->getJson('/api/reservations/'.$res['reservation_id'])->assertJsonPath('status', 'PENDING');
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
        Http::fake(function () {
            throw new ConnectionException('down');
        });
        $res = $this->reserve()->json();
        $this->api()->postJson('/api/payments', $this->cardPayment($res['reservation_id'], $res['total_amount']))
            ->assertStatus(502)->assertJsonPath('error', 'Payment service is unavailable');
    }

    public function test_reservation_can_be_cancelled(): void
    {
        $res = $this->reserve()->json();
        $this->api()->deleteJson('/api/reservations/'.$res['reservation_id'])->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    public function test_api_gcash_and_maya_with_mobile_numbers_still_work(): void
    {
        foreach (['gcash', 'maya'] as $index => $method) {
            Http::fake(['*' => Http::response(['transaction_id' => "TXN-{$method}", 'status' => 'APPROVED', 'details' => ucfirst($method)], 201)]);
            $start = 10 + ($index * 10);
            $res = $this->reserve(['check_in' => now()->addDays($start)->format('Y-m-d'), 'check_out' => now()->addDays($start + 2)->format('Y-m-d')])->json();
            $payload = ['reservation_id' => $res['reservation_id'], 'amount' => $res['total_amount'], 'method' => $method, 'mobile_number' => '+639171234567'];
            $this->api()->postJson('/api/payments', $payload)->assertStatus(201)->assertJsonPath('method', $method);
            Http::assertSent(fn ($req) => $req['method'] === $method && $req['mobile_number'] === '09171234567');
        }
    }

    public function test_qr_session_validates_method_ties_owner_and_uses_server_amount(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->postJson('/pay/RSV-1001/session', ['method' => 'gcash', 'amount' => 1])->assertOk()->assertJsonPath('amount', 5000);
        $this->postJson('/pay/RSV-1001/session', ['method' => 'cash'])->assertStatus(422);

        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/pay/RSV-1001/session', ['method' => 'maya'])->assertNotFound();
    }

    public function test_expired_qr_cannot_be_paid_and_paid_session_cannot_be_paid_twice(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reserve', $this->booking());
        $created = $this->postJson('/pay/RSV-1001/session', ['method' => 'maya'])->assertOk();
        $session = $created->json('session_id');

        $this->postJson("/pay/RSV-1001/simulate/{$session}")->assertOk()->assertJsonPath('status', 'PAID');
        $this->postJson("/pay/RSV-1001/simulate/{$session}")->assertOk()->assertJsonPath('status', 'PAID');

        $this->actingAs(User::factory()->create())->postJson("/pay/RSV-1001/simulate/{$session}")->assertNotFound();

        $expiredUser = User::factory()->create();
        $this->actingAs($expiredUser)->post('/reserve', $this->booking(['guest_email' => 'expired@example.com', 'check_in' => now()->addDays(30)->format('Y-m-d'), 'check_out' => now()->addDays(32)->format('Y-m-d')]));
        $expired = $this->postJson('/pay/RSV-1002/session', ['method' => 'gcash'])->assertOk();
        $expiredToken = $expired->json('session_id');
        \App\Models\PaymentSession::where('session_token', $expiredToken)->update(['expires_at' => now()->subMinute()]);
        $this->postJson("/pay/RSV-1002/simulate/{$expiredToken}")->assertStatus(410);
    }

    public function test_website_booking_flow_with_gcash_qr(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->assertSame($user->id, Reservation::firstOrFail()->user_id);
        $this->get('/pay/RSV-1001')->assertOk()->assertSee('How would you like to pay?')->assertDontSee('mobile_number')->assertSee('qr-image-wrap', false);

        $created = $this->postJson('/pay/RSV-1001/session', ['method' => 'gcash'])
            ->assertOk()->assertJsonPath('provider', 'gcash')->assertJsonStructure(['session_id', 'qr_svg', 'amount', 'expires_at']);
        $session = $created->json('session_id');
        $this->assertDatabaseHas('reservations', ['id' => 1, 'status' => 'PENDING']);

        $this->postJson("/pay/RSV-1001/simulate/{$session}")
            ->assertOk()->assertJsonPath('status', 'PAID');
        Mail::assertSentCount(1);
        Mail::assertSent(BookingConfirmation::class, fn (BookingConfirmation $mail) => $mail->hasTo('juan@example.com')
            && $mail->payment->method === 'gcash'
            && $mail->reservation->check_in->format('Y-m-d') === now()->addDays(2)->format('Y-m-d'));
        $this->assertDatabaseHas('reservations', ['status' => 'CONFIRMED']);
        $this->get('/done/RSV-1001')->assertOk()->assertSee('Your booking is confirmed')->assertSee('img/logo.png', false);

        $this->postJson("/pay/RSV-1001/simulate/{$session}")->assertOk()->assertJsonPath('status', 'PAID');
    }

    public function test_booked_dates_endpoint_requires_login_and_returns_only_date_ranges(): void
    {
        $this->get('/book/R101/booked-dates')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user);

        Reservation::create([
            'room_id' => 'R101', 'guest_name' => 'Secret Name', 'guest_email' => 'secret@example.com',
            'check_in' => now()->addDays(5)->format('Y-m-d'), 'check_out' => now()->addDays(8)->format('Y-m-d'),
            'guests' => 2, 'nights' => 3, 'total_amount' => 9000, 'currency' => 'PHP', 'status' => 'CONFIRMED',
        ]);
        Reservation::create([
            'room_id' => 'R101', 'guest_name' => 'Cancelled', 'guest_email' => 'cancelled@example.com',
            'check_in' => now()->addDays(10)->format('Y-m-d'), 'check_out' => now()->addDays(12)->format('Y-m-d'),
            'guests' => 1, 'nights' => 2, 'total_amount' => 6000, 'currency' => 'PHP', 'status' => 'CANCELLED',
        ]);
        Reservation::create([
            'room_id' => 'R202', 'guest_name' => 'Other Room', 'guest_email' => 'other@example.com',
            'check_in' => now()->addDays(15)->format('Y-m-d'), 'check_out' => now()->addDays(17)->format('Y-m-d'),
            'guests' => 1, 'nights' => 2, 'total_amount' => 8000, 'currency' => 'PHP', 'status' => 'CONFIRMED',
        ]);

        $response = $this->getJson('/book/R101/booked-dates')->assertOk()->assertJsonStructure(['ranges', 'available']);
        $response->assertJsonCount(0, 'ranges');
        $this->assertStringNotContainsString('Secret Name', $response->getContent());
        $this->assertStringNotContainsString('secret@example.com', $response->getContent());
    }

    public function test_back_to_back_stay_is_allowed_but_overlap_is_rejected(): void
    {
        $service = $this->app->make(BookingService::class);
        Reservation::create([
            'room_id' => 'R101', 'guest_name' => 'Existing Guest', 'guest_email' => 'existing@example.com',
            'check_in' => now()->addDays(5)->format('Y-m-d'), 'check_out' => now()->addDays(7)->format('Y-m-d'),
            'guests' => 2, 'nights' => 2, 'total_amount' => 6000, 'currency' => 'PHP', 'status' => 'CONFIRMED',
        ]);
        $in = now()->addDays(5)->format('Y-m-d');
        $out = now()->addDays(7)->format('Y-m-d');
        $this->assertTrue($service->isBooked('R101', $in, $out));
        $this->assertFalse($service->isBooked('R101', now()->addDays(3)->format('Y-m-d'), $in));
        $this->assertFalse($service->isBooked('R101', $out, now()->addDays(9)->format('Y-m-d')));
        $this->assertTrue($service->isBooked('R101', now()->addDays(6)->format('Y-m-d'), now()->addDays(8)->format('Y-m-d')));
    }

    public function test_initial_inventory_and_date_based_availability_are_calculated(): void
    {
        $this->assertDatabaseHas('rooms', ['id' => 'R101', 'inventory_capacity' => 15]);
        $this->assertDatabaseHas('rooms', ['id' => 'R202', 'inventory_capacity' => 10]);
        $this->assertDatabaseHas('rooms', ['id' => 'R303', 'inventory_capacity' => 15]);
        $this->assertDatabaseHas('rooms', ['id' => 'R404', 'inventory_capacity' => 6]);

        $room = Room::findOrFail('R101');
        for ($i = 0; $i < 10; $i++) {
            Reservation::create([
                'room_id' => 'R101', 'guest_name' => "Guest {$i}", 'guest_email' => "guest{$i}@example.com",
                'check_in' => now()->addDays(20)->format('Y-m-d'), 'check_out' => now()->addDays(22)->format('Y-m-d'),
                'guests' => 1, 'nights' => 2, 'total_amount' => 5000, 'currency' => 'PHP', 'status' => 'CONFIRMED',
            ]);
        }

        $service = $this->app->make(BookingService::class);
        $this->assertSame(5, $service->availableRooms($room, now()->addDays(20)->format('Y-m-d'), now()->addDays(22)->format('Y-m-d')));

        Reservation::where('room_id', 'R101')->where('status', 'CONFIRMED')->update(['status' => 'CANCELLED']);
        $room->refresh();
        $this->assertSame(15, $service->availableRooms($room, now()->addDays(20)->format('Y-m-d'), now()->addDays(22)->format('Y-m-d')));
    }
}
