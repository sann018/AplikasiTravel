<?php

namespace Tests\Feature;

use App\Models\Jadwal;
use App\Models\Rute;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleLocationContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_schedule_is_rejected_when_driver_and_vehicle_are_still_in_another_city(): void
    {
        [$admin, $previousSchedule, $pontianakSanggau] = $this->preparePreviousPontianakSintangTrip();
        $scheduleCount = Jadwal::query()->count();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.create'))
            ->post(route('admin.jadwal.store'), [
                'kendaraan_id' => $previousSchedule->kendaraan_id,
                'rute_id' => $pontianakSanggau->id,
                'pengemudi_id' => $previousSchedule->pengemudi_id,
                'waktu_berangkat' => '2099-08-10 18:00',
                'status' => 'dibuka',
            ]);

        $response
            ->assertRedirect(route('admin.jadwal.create'))
            ->assertSessionHasErrors('pengemudi_id');
        $this->assertStringContainsString(
            'Sintang',
            session('errors')->first('pengemudi_id'),
        );
        $this->assertSame($scheduleCount, Jadwal::query()->count());
    }

    public function test_return_trip_from_last_destination_is_accepted_after_two_hour_break(): void
    {
        [$admin, $previousSchedule] = $this->preparePreviousPontianakSintangTrip();
        $returnRoute = Rute::query()->create([
            'kota_asal' => 'Sintang',
            'kota_tujuan' => 'Pontianak',
            'deskripsi_jalur' => 'Rute balik untuk pengujian kesinambungan lokasi.',
            'jarak_km' => 320,
            'estimasi_durasi_menit' => 480,
            'harga_tiket' => 225000,
            'status_aktif' => true,
        ]);
        $scheduleCount = Jadwal::query()->count();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.create'))
            ->post(route('admin.jadwal.store'), [
                'kendaraan_id' => $previousSchedule->kendaraan_id,
                'rute_id' => $returnRoute->id,
                'pengemudi_id' => $previousSchedule->pengemudi_id,
                'waktu_berangkat' => '2099-08-10 18:00',
                'status' => 'dibuka',
            ]);

        $response
            ->assertRedirect(route('admin.jadwal.index'))
            ->assertSessionHasNoErrors();
        $this->assertSame($scheduleCount + 1, Jadwal::query()->count());
    }

    public function test_matching_location_is_still_rejected_when_break_is_less_than_two_hours(): void
    {
        [$admin, $previousSchedule] = $this->preparePreviousPontianakSintangTrip();
        $returnRoute = Rute::query()->create([
            'kota_asal' => 'Sintang',
            'kota_tujuan' => 'Pontianak',
            'deskripsi_jalur' => 'Rute balik untuk pengujian waktu jeda.',
            'jarak_km' => 320,
            'estimasi_durasi_menit' => 480,
            'harga_tiket' => 225000,
            'status_aktif' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.create'))
            ->post(route('admin.jadwal.store'), [
                'kendaraan_id' => $previousSchedule->kendaraan_id,
                'rute_id' => $returnRoute->id,
                'pengemudi_id' => $previousSchedule->pengemudi_id,
                'waktu_berangkat' => '2099-08-10 17:00',
                'status' => 'dibuka',
            ]);

        $response
            ->assertRedirect(route('admin.jadwal.create'))
            ->assertSessionHasErrors('pengemudi_id');
        $this->assertStringContainsString(
            'jeda minimal 2 jam',
            session('errors')->first('pengemudi_id'),
        );
    }

    public function test_inserted_schedule_is_rejected_when_its_destination_breaks_next_trip_location(): void
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $nextSchedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $pontianakSintang = Rute::query()
            ->where('kota_asal', 'Pontianak')
            ->where('kota_tujuan', 'Sintang')
            ->firstOrFail();
        $sanggauPontianak = Rute::query()->create([
            'kota_asal' => 'Sanggau',
            'kota_tujuan' => 'Pontianak',
            'deskripsi_jalur' => 'Rute lanjutan untuk pengujian posisi berikutnya.',
            'jarak_km' => 190,
            'estimasi_durasi_menit' => 315,
            'harga_tiket' => 165000,
            'status_aktif' => true,
        ]);

        $this->cancelOtherResourceSchedules($nextSchedule);
        $nextSchedule->update([
            'rute_id' => $sanggauPontianak->id,
            'waktu_berangkat' => '2099-08-11 18:00:00',
            'estimasi_tiba' => '2099-08-11 23:15:00',
            'status' => 'dibuka',
        ]);
        $scheduleCount = Jadwal::query()->count();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.jadwal.create'))
            ->post(route('admin.jadwal.store'), [
                'kendaraan_id' => $nextSchedule->kendaraan_id,
                'rute_id' => $pontianakSintang->id,
                'pengemudi_id' => $nextSchedule->pengemudi_id,
                'waktu_berangkat' => '2099-08-11 08:00',
                'status' => 'dibuka',
            ]);

        $response
            ->assertRedirect(route('admin.jadwal.create'))
            ->assertSessionHasErrors('pengemudi_id');
        $this->assertStringContainsString(
            'jadwal berikutnya berangkat dari Sanggau',
            session('errors')->first('pengemudi_id'),
        );
        $this->assertSame($scheduleCount, Jadwal::query()->count());
    }

    /**
     * @return array{0: User, 1: Jadwal, 2: Rute}
     */
    private function preparePreviousPontianakSintangTrip(): array
    {
        $admin = User::query()->where('email', 'admin@travs.com')->firstOrFail();
        $previousSchedule = Jadwal::query()->where('status', 'dibuka')->firstOrFail();
        $pontianakSintang = Rute::query()
            ->where('kota_asal', 'Pontianak')
            ->where('kota_tujuan', 'Sintang')
            ->firstOrFail();
        $pontianakSanggau = Rute::query()
            ->where('kota_asal', 'Pontianak')
            ->where('kota_tujuan', 'Sanggau')
            ->firstOrFail();

        $this->cancelOtherResourceSchedules($previousSchedule);
        $previousSchedule->update([
            'rute_id' => $pontianakSintang->id,
            'waktu_berangkat' => '2099-08-10 08:00:00',
            'estimasi_tiba' => '2099-08-10 16:00:00',
            'status' => 'selesai',
        ]);

        return [$admin, $previousSchedule->fresh(), $pontianakSanggau];
    }

    private function cancelOtherResourceSchedules(Jadwal $referenceSchedule): void
    {
        Jadwal::query()
            ->whereKeyNot($referenceSchedule->id)
            ->where(function ($query) use ($referenceSchedule) {
                $query
                    ->where('pengemudi_id', $referenceSchedule->pengemudi_id)
                    ->orWhere('kendaraan_id', $referenceSchedule->kendaraan_id);
            })
            ->update(['status' => 'dibatalkan']);
    }
}
