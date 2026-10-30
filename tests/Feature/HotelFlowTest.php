<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\Reservation;
use App\Models\User;
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
        $this->get('/?check_in='.now()->addDay()->format('Y-m-d').'&check_out='.now()->addDays(3)->format('Y-m-d').'&guests=2')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Log in');
        $this->get('/register')->assertOk()->assertSee('Create an account');
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
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/reservations')->assertOk();
        $this->get('/admin/rooms')->assertOk();
        $this->get('/admin/users')->assertOk();
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
            'available' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R505', 'price_per_night' => 3200, 'available' => true]);

        $this->put('/admin/rooms/R505', [
            'name' => 'Garden Room Deluxe',
            'type' => 'Deluxe',
            'price_per_night' => 4000,
            'capacity' => 3,
            'available' => '0',
        ])->assertRedirect();
        $this->assertDatabaseHas('rooms', ['id' => 'R505', 'price_per_night' => 4000, 'available' => false]);
        $this->get('/admin/rooms')->assertOk()->assertSee('Fully booked');
        $this->get('/book/R505')->assertRedirect(route('rooms'));

        $this->put('/admin/rooms/R505', [
            'name' => 'Garden Room Deluxe',
            'type' => 'Deluxe',
            'price_per_night' => 4000,
            'capacity' => 3,
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
        $this->api()->getJson('/api/reservations/'.$res['reservation_id'])->assertJsonPath('status', 'CONFIRMED');

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

    public function test_website_booking_flow_with_gcash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Http::fake(['*' => Http::response(['transaction_id' => 'TXN-2', 'status' => 'APPROVED', 'details' => 'GCash 0917***4567'], 201)]);

        $this->post('/reserve', $this->booking())->assertRedirect('/pay/RSV-1001');
        $this->assertSame($user->id, Reservation::firstOrFail()->user_id);
        $this->get('/pay/RSV-1001')->assertOk()->assertSee('How would you like to pay?');
        $this->post('/pay/RSV-1001', ['method' => 'gcash', 'mobile_number' => '+639171234567'])->assertRedirect('/done/RSV-1001');
        Http::assertSent(fn ($req) => $req['mobile_number'] === '09171234567');
        $this->get('/done/RSV-1001')->assertOk()->assertSee('Your booking is confirmed');
    }
}
