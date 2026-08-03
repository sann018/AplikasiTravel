<?php

namespace Tests\Feature;

use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalExpenseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_requires_and_stores_receipt_without_verification_flow(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'BBM',
                'nominal' => 150000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Pengisian BBM yang dibayar langsung oleh perusahaan.',
            ])
            ->assertSessionHasErrors('foto_bukti_nota');

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'BBM',
                'nominal' => 150000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Pengisian BBM yang dibayar langsung oleh perusahaan.',
                'foto_bukti_nota' => UploadedFile::fake()->create(
                    'nota-bbm.pdf',
                    100,
                    'application/pdf',
                ),
            ])
            ->assertSessionHasErrors('foto_bukti_nota');

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'BBM',
                'nominal' => 150000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Pengisian BBM yang dibayar langsung oleh perusahaan.',
                'foto_bukti_nota' => UploadedFile::fake()->image('nota-bbm.jpg', 900, 600),
            ])
            ->assertRedirect(route('admin.biaya.index'));

        $expense = BiayaOperasionalPerjalanan::query()
            ->where('jenis_biaya', 'BBM')
            ->firstOrFail();

        foreach (['status_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada', 'catatan_verifikasi'] as $column) {
            $this->assertFalse(Schema::hasColumn('biaya_operasional_perjalanan', $column));
        }
        $this->assertSame($admin->id, $expense->created_by);
        $this->assertNotNull($expense->bukti_url);
        Storage::disk('public')->assertExists($expense->bukti_url);

        $this
            ->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('BBM')
            ->assertSee('Dicatat admin')
            ->assertSee('Lihat Bukti')
            ->assertDontSee('Menunggu Verifikasi')
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak');
    }

    public function test_operational_expense_page_explains_direct_recording_flow(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $this->assertFalse(Route::has('admin.biaya.verify'));

        $this
            ->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('Tambah Biaya')
            ->assertSee('Semua rute')
            ->assertSee('Tampilkan Semua')
            ->assertDontSee('Semua jadwal')
            ->assertDontSee('Catat Biaya Operasional')
            ->assertDontSee('Foto Bukti Nota');

        $this
            ->actingAs($admin)
            ->get(route('admin.biaya.create'))
            ->assertOk()
            ->assertSee('Tambah Biaya Operasional')
            ->assertSee('Pilih kategori pengeluaran')
            ->assertSee('name="jenis_biaya"', false)
            ->assertSee('Foto Bukti/Nota')
            ->assertSee('name="foto_bukti_nota"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak');
    }

    public function test_admin_can_manage_dropdown_categories_for_web_and_mobile(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.categories.store'), [
                'nama_kategori' => 'Cuci Kendaraan',
            ])
            ->assertRedirect(route('admin.biaya.index'))
            ->assertSessionHasNoErrors();

        $storedCategories = json_decode(
            (string) SystemSetting::query()
                ->where('kunci', 'operational_expense_categories')
                ->value('nilai'),
            true,
        );
        $this->assertContains('Cuci Kendaraan', $storedCategories);

        $this
            ->actingAs($admin)
            ->get(route('admin.biaya.create'))
            ->assertOk()
            ->assertSee('Cuci Kendaraan');

        $this
            ->getJson('/api/settings/mobile')
            ->assertOk()
            ->assertJsonFragment([
                'operational_expense_categories' => [
                    'BBM',
                    'Tol',
                    'Parkir',
                    'Makan Pengemudi',
                    'Perawatan Darurat',
                    'Lainnya',
                    'Cuci Kendaraan',
                ],
            ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.biaya.categories.destroy'), [
                'nama_kategori' => 'Cuci Kendaraan',
            ])
            ->assertRedirect(route('admin.biaya.index'))
            ->assertSessionHasNoErrors();

        $this->assertNotContains(
            'Cuci Kendaraan',
            json_decode(
                (string) SystemSetting::query()
                    ->where('kunci', 'operational_expense_categories')
                    ->value('nilai'),
                true,
            ),
        );
    }

    public function test_admin_cannot_record_cost_with_category_outside_managed_dropdown(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'Kategori bebas',
                'nominal' => 10000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Pengeluaran di luar kategori yang dikelola.',
            ])
            ->assertSessionHasErrors('jenis_biaya');

        $this->assertDatabaseMissing('biaya_operasional_perjalanan', [
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => 'Kategori bebas',
        ]);
    }

    public function test_admin_can_correct_and_delete_recorded_cost(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();
        $oldProofPath = 'biaya-operasional/nota-parkir-lama.jpg';
        Storage::disk('public')->put($oldProofPath, 'bukti-lama');
        $expense = BiayaOperasionalPerjalanan::query()->create([
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => 'Parkir',
            'nominal' => 10000,
            'tanggal_biaya' => now(),
            'keterangan' => 'Biaya parkir kendaraan saat perjalanan.',
            'bukti_url' => $oldProofPath,
            'created_by' => $admin->id,
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.biaya.update', $expense), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'Tol',
                'nominal' => 12000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Koreksi biaya parkir kendaraan di terminal.',
                'foto_bukti_nota' => UploadedFile::fake()->image('nota-tol-baru.jpg', 900, 600),
            ])
            ->assertRedirect(route('admin.biaya.index'));

        $expense->refresh();
        $this->assertDatabaseHas('biaya_operasional_perjalanan', [
            'id' => $expense->id,
            'jenis_biaya' => 'Tol',
            'nominal' => 12000,
        ]);
        $this->assertNotSame($oldProofPath, $expense->bukti_url);
        Storage::disk('public')->assertMissing($oldProofPath);
        Storage::disk('public')->assertExists($expense->bukti_url);

        $this
            ->actingAs($admin)
            ->delete(route('admin.biaya.destroy', $expense))
            ->assertRedirect();

        $this->assertDatabaseMissing('biaya_operasional_perjalanan', [
            'id' => $expense->id,
        ]);
        Storage::disk('public')->assertMissing($expense->bukti_url);
    }
}
