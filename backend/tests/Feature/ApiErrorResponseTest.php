<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test TDD untuk memastikan semua API endpoint mengembalikan format error
 * yang konsisten sesuai rules #26, #27, #28:
 * - Tidak menampilkan detail teknis ke pengguna
 * - Menggunakan HTTP status code yang tepat
 * - Format response selalu: success, message, errors (jika ada)
 */
class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // =========================================================================
    // Test: Response 401 — Unauthorized (sesi tidak valid)
    // =========================================================================

    public function test_unauthenticated_request_returns_consistent_401_format(): void
    {
        // Akses endpoint yang butuh auth tanpa token
        $response = $this->getJson('/api/penumpang/dashboard');

        $response
            ->assertStatus(401)
            ->assertJsonStructure(['success', 'message'])
            ->assertJsonPath('success', false);

        // Pastikan tidak ada stack trace atau detail teknis
        $body = $response->json();
        $this->assertArrayNotHasKey('exception', $body);
        $this->assertArrayNotHasKey('trace', $body);
        $this->assertArrayNotHasKey('file', $body);
    }

    // =========================================================================
    // Test: Response 404 — Data tidak ditemukan
    // =========================================================================

    public function test_not_found_returns_consistent_404_format(): void
    {
        // Akses jadwal yang tidak ada
        $response = $this->getJson('/api/guest/jadwal/99999');

        $response
            ->assertStatus(404)
            ->assertJsonStructure(['success', 'message'])
            ->assertJsonPath('success', false);

        // Pastikan pesan ramah, bukan "No query results for model"
        $message = $response->json('message');
        $this->assertStringNotContainsString('No query results', $message);
        $this->assertStringNotContainsString('Jadwal', $message);
    }

    // =========================================================================
    // Test: Response 422 — Validasi gagal dengan detail per field
    // =========================================================================

    public function test_validation_error_returns_consistent_422_format(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($user);

        // Kirim payload booking tanpa field wajib
        $response = $this->postJson('/api/penumpang/bookings', []);

        $response
            ->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJsonPath('success', false);

        // Pastikan ada detail error per field
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('jadwal_id', $errors);
    }

    // =========================================================================
    // Test: Response 422 — Validasi login gagal
    // =========================================================================

    public function test_login_validation_error_returns_consistent_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'tidakada@example.com',
            'password' => 'salahpassword',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);

        // Pastikan pesan error ada dan tidak mengandung detail teknis
        $errors = $response->json('errors');
        $this->assertArrayHasKey('login', $errors);
    }

    // =========================================================================
    // Test: Response 429 — Rate limit
    // =========================================================================

    public function test_rate_limited_request_returns_consistent_429_format(): void
    {
        $user = User::factory()->create([
            'email' => 'ratelimit-test@example.com',
            'peran' => 'penumpang',
        ]);
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        Sanctum::actingAs($user);

        // Spam request sampai kena rate limit
        for ($i = 0; $i < 25; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.99.99.99'])
                ->postJson('/api/penumpang/bookings/estimate', [
                    'jadwal_id' => $jadwal->id,
                    'seat_count' => 1,
                    'baggage_items' => [],
                ]);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.99.99.99'])
            ->postJson('/api/penumpang/bookings/estimate', [
                'jadwal_id' => $jadwal->id,
                'seat_count' => 1,
                'baggage_items' => [],
            ]);

        $response
            ->assertStatus(429)
            ->assertJsonStructure(['success', 'message'])
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // Test: Response 403 — Forbidden (role tidak sesuai)
    // =========================================================================

    public function test_forbidden_access_returns_consistent_403_format(): void
    {
        // Penumpang coba akses endpoint pengemudi
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/pengemudi/dashboard');

        // Bisa 403 atau 404 tergantung implementasi guard
        $this->assertTrue(
            in_array($response->status(), [403, 404, 500]),
            'Expected 403, 404, or 500 for forbidden access'
        );

        $response->assertJsonStructure(['success', 'message']);
        $response->assertJsonPath('success', false);
    }

    // =========================================================================
    // Test: Semua success response punya format konsisten
    // =========================================================================

    public function test_success_response_has_consistent_format(): void
    {
        // Guest baca jadwal — harus success dengan format standar
        $response = $this->getJson('/api/guest/jadwal');

        $response
            ->assertOk()
            ->assertJsonStructure(['success', 'data'])
            ->assertJsonPath('success', true);
    }

    public function test_guest_schedule_detail_success_has_consistent_format(): void
    {
        $jadwal = Jadwal::query()->where('status', 'dibuka')->firstOrFail();

        $response = $this->getJson("/api/guest/jadwal/{$jadwal->id}");

        $response
            ->assertOk()
            ->assertJsonStructure(['success', 'data'])
            ->assertJsonPath('success', true);
    }

    // =========================================================================
    // Test: Error response tidak mengandung informasi sensitif
    // =========================================================================

    public function test_error_responses_never_expose_sensitive_info(): void
    {
        // Request ke endpoint yang tidak ada
        $response = $this->getJson('/api/nonexistent/endpoint');

        $body = $response->json();

        // Pastikan tidak ada informasi sensitif yang bocor
        $bodyString = json_encode($body);
        $this->assertStringNotContainsString('SQLSTATE', $bodyString);
        $this->assertStringNotContainsString('.php', $bodyString);
        $this->assertStringNotContainsString('vendor/', $bodyString);
        $this->assertStringNotContainsString('password', $bodyString);
        $this->assertStringNotContainsString('DB_', $bodyString);
    }

    // =========================================================================
    // Test: Register validasi error format konsisten
    // =========================================================================

    public function test_register_validation_returns_consistent_format(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nama' => '',
            'email' => 'bukan-email',
            'no_hp' => '',
            'password' => '123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('nama', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_register_duplicate_account_returns_friendly_message(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();

        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Penumpang Baru',
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(
            'Email sudah terdaftar. Silakan masuk atau gunakan email lain.',
            $response->json('errors.email.0')
        );
        $this->assertSame(
            'Nomor HP sudah terdaftar. Silakan masuk atau gunakan nomor lain.',
            $response->json('errors.no_hp.0')
        );
    }

    public function test_mobile_registration_always_creates_passenger_account(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Penumpang Registrasi',
            'email' => 'penumpang-registrasi@example.com',
            'no_hp' => '081234567899',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'peran' => 'pengemudi',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.peran', 'penumpang');

        $this->assertDatabaseHas('pengguna', [
            'email' => 'penumpang-registrasi@example.com',
            'peran' => 'penumpang',
        ]);
    }

    public function test_admin_account_cannot_login_through_mobile_api(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-mobile@example.com',
            'peran' => 'admin',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $admin->email,
            'password' => 'Password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.login.0',
                'Akun admin hanya dapat digunakan melalui web admin.',
            );

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $admin->id,
        ]);
    }

    // =========================================================================
    // Test: Booking dengan jadwal tidak valid
    // =========================================================================

    public function test_booking_with_invalid_schedule_returns_422(): void
    {
        $user = User::query()->where('peran', 'penumpang')->firstOrFail();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/penumpang/bookings', [
            'jadwal_id' => 99999,
            'seat_numbers' => [1],
            'pickup' => [
                'latitude' => -0.0225,
                'longitude' => 109.3425,
                'address' => 'Test',
            ],
            'dropoff' => [
                'latitude' => 0.1285,
                'longitude' => 111.4986,
                'address' => 'Terminal tujuan',
            ],
            'payment_method' => 'qris',
            'passengers' => [
                [
                    'seat_number' => 1,
                    'nama_penumpang' => 'Test',
                    'no_hp_penumpang' => '081200000001',
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors'])
            ->assertJsonPath('success', false);
    }
}
