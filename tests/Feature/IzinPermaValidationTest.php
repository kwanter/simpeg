<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Carbon\Carbon;
use Tests\SimpegTestCase;

class IzinPermaValidationTest extends SimpegTestCase
{
    private function createPegawaiUser(): array
    {
        $user = $this->createUserWithRole('pegawai', ['status' => '1']);
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        // Grant create izin permission if needed
        $user->givePermissionTo('create izin');

        return [$user, $pegawai];
    }

    private function createApprover(string $role): Pegawai
    {
        $user = $this->createUserWithRole($role);

        return Pegawai::where('nip', $user->nip)->firstOrFail();
    }

    public function test_izin_tidak_masuk_lebih_dari_dua_hari_kerja_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // Mon Jan 5 → Wed Jan 7 = 3 workdays
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-07',
            'alasan' => 'Keperluan keluarga mendesak',
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');

        Carbon::setTestNow();
    }

    public function test_izin_tidak_masuk_dua_hari_kerja_diperbolehkan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // Mon Jan 5 → Tue Jan 6 = 2 workdays (valid)
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Keperluan keluarga',
        ]);

        $response->assertSessionHasNoErrors();

        // Check database
        $this->assertDatabaseHas('izin', [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'pegawai_uuid' => $pegawai->uuid,
            'jumlah_hari' => 2,
            'status' => 'Diajukan',
        ]);

        Carbon::setTestNow();
    }

    public function test_izin_keluar_kantor_tidak_boleh_mulai_besok(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // tanggal_mulai = tomorrow (not today) → should fail
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-06',
            'tanggal_selesai' => '2026-01-06',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'alasan' => 'Ke luar sebentar',
        ]);

        $response->assertSessionHasErrors(['tanggal_mulai', 'tanggal_selesai']);

        Carbon::setTestNow();
    }

    public function test_izin_keluar_kantor_valid_same_day_request_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // Today, valid time range
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
            'jam_mulai' => '10:00',
            'jam_selesai' => '11:00',
            'alasan' => 'Ke dokter',
        ]);

        $response->assertSessionHasNoErrors();

        // Overridden by controller to today's date + jumlah_hari = 0
        $this->assertDatabaseHas('izin', [
            'jenis_izin' => 'Izin Keluar Kantor',
            'pegawai_uuid' => $pegawai->uuid,
            'jumlah_hari' => 0,
            'status' => 'Diajukan',
        ]);

        Carbon::setTestNow();
    }

    public function test_izin_keluar_kantor_jam_selesai_sebelum_jam_mulai_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // jam_selesai before jam_mulai → should fail
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Keluar Kantor',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
            'jam_mulai' => '14:00',
            'jam_selesai' => '10:00', // before jam_mulai
            'alasan' => 'Test',
        ]);

        $response->assertSessionHasErrors('jam_selesai');

        Carbon::setTestNow();
    }

    public function test_izin_pulang_cepat_valid_same_day_request_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Pulang Cepat',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-05',
            'jam_mulai' => '15:00',
            'jam_selesai' => '16:00',
            'alasan' => 'Pulang cepat',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('izin', [
            'jenis_izin' => 'Izin Pulang Cepat',
            'pegawai_uuid' => $pegawai->uuid,
            'jumlah_hari' => 0,
            'status' => 'Diajukan',
        ]);

        Carbon::setTestNow();
    }

    public function test_izin_tidak_masuk_today_and_tomorrow_is_two_workdays(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05')); // Monday

        [$user, $pegawai] = $this->createPegawaiUser();
        $atasan = $this->createApprover('atasan-pimpinan');
        $pimpinan = $this->createApprover('pimpinan');

        // Today (Mon) + Tomorrow (Tue) = exactly 2 workdays
        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Dua hari',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('izin', [
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
            'pegawai_uuid' => $pegawai->uuid,
            'jumlah_hari' => 2,
        ]);

        Carbon::setTestNow();
    }
}
