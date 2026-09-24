<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Support\IzinType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\SimpegTestCase;

class IzinPermaCapTest extends SimpegTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_store_rejects_a_tidak_masuk_payload_beyond_the_cap_without_creating_a_row(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $user = $this->createUserWithRole('user');
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'jenis_izin' => IzinType::TIDAK_MASUK,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-07',
            'alasan' => 'Three workdays',
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');
        $this->assertSame(
            'Izin tidak masuk kerja maksimal 2 (dua) hari kerja.',
            session('errors')->first('tanggal_selesai')
        );
        $this->assertSame(0, Izin::count());
    }

    public function test_full_update_rejects_a_tidak_masuk_payload_beyond_the_cap_and_leaves_the_row_unchanged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));

        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Original reason',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        // Compare the raw stored row rather than the hydrated model: date casts
        // return Carbon instances, and assertSame on arrays compares identity.
        $snapshot = ['jenis_izin', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari', 'alasan'];
        $before = (array) DB::table('izin')->where('uuid', $izin->uuid)->first($snapshot);

        $response = $this->actingAs($user)->put(route('izin.update', $izin->uuid), [
            'jenis_izin' => IzinType::TIDAK_MASUK,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-07',
            'alasan' => 'Changed reason',
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');
        $this->assertSame(
            'Izin tidak masuk kerja maksimal 2 (dua) hari kerja.',
            session('errors')->first('tanggal_selesai')
        );
        $this->assertSame(
            $before,
            (array) DB::table('izin')->where('uuid', $izin->uuid)->first($snapshot)
        );
    }
}
