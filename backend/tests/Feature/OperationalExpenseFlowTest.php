<?php

namespace Tests\Feature;

use App\Models\BiayaOperasionalPerjalanan;
use App\Models\Jadwal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_records_cost_directly_without_verification_flow(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'BBM perusahaan',
                'nominal' => 150000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Pengisian BBM yang dibayar langsung oleh perusahaan.',
                'bukti_nota' => UploadedFile::fake()->image('nota-bbm.jpg'),
            ])
            ->assertRedirect(route('admin.biaya.index'));

        $expense = BiayaOperasionalPerjalanan::query()
            ->where('jenis_biaya', 'BBM perusahaan')
            ->firstOrFail();

        $this->assertSame('disetujui', $expense->status_verifikasi);
        $this->assertNull($expense->diverifikasi_oleh);
        $this->assertNull($expense->diverifikasi_pada);
        $this->assertSame($admin->id, $expense->created_by);
        $this->assertNotNull($expense->bukti_url);
        Storage::disk('public')->assertExists($expense->bukti_url);

        $this
            ->actingAs($admin)
            ->get(route('admin.biaya.index'))
            ->assertOk()
            ->assertSee('BBM perusahaan')
            ->assertSee('Dicatat admin')
            ->assertSee('Lihat Bukti')
            ->assertDontSee('Menunggu Verifikasi')
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak');
    }

    public function test_operational_expense_page_explains_direct_recording_flow(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();

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
            ->assertSee('Foto Bukti Nota')
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak');
    }

    public function test_admin_must_attach_receipt_when_recording_operational_expense(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('admin.biaya.store'), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'Parkir',
                'nominal' => 10000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Biaya parkir kendaraan di terminal.',
            ])
            ->assertSessionHasErrors('bukti_nota');

        $this->assertDatabaseMissing('biaya_operasional_perjalanan', [
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => 'Parkir',
        ]);
    }

    public function test_admin_can_correct_and_delete_recorded_cost(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $jadwal = Jadwal::query()->firstOrFail();
        $expense = BiayaOperasionalPerjalanan::query()->create([
            'jadwal_id' => $jadwal->id,
            'jenis_biaya' => 'Parkir',
            'nominal' => 10000,
            'tanggal_biaya' => now(),
            'keterangan' => 'Biaya parkir kendaraan saat perjalanan.',
            'bukti_url' => 'biaya-operasional/nota-parkir-lama.jpg',
            'created_by' => $admin->id,
            'status_verifikasi' => 'disetujui',
        ]);

        $this
            ->actingAs($admin)
            ->put(route('admin.biaya.update', $expense), [
                'jadwal_id' => $jadwal->id,
                'jenis_biaya' => 'Parkir terminal',
                'nominal' => 12000,
                'tanggal_biaya' => now()->format('Y-m-d\TH:i'),
                'keterangan' => 'Koreksi biaya parkir kendaraan di terminal.',
            ])
            ->assertRedirect(route('admin.biaya.index'));

        $this->assertDatabaseHas('biaya_operasional_perjalanan', [
            'id' => $expense->id,
            'jenis_biaya' => 'Parkir terminal',
            'nominal' => 12000,
            'status_verifikasi' => 'disetujui',
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('admin.biaya.destroy', $expense))
            ->assertRedirect();

        $this->assertDatabaseMissing('biaya_operasional_perjalanan', [
            'id' => $expense->id,
        ]);
    }
}
