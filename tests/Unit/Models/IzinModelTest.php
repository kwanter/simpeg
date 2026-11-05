<?php

namespace Tests\Unit\Models;

use App\Models\Izin;
use App\Models\Pegawai;
use Tests\SimpegTestCase;

class IzinModelTest extends SimpegTestCase
{
    public function test_model_persists_with_uuid_as_a_non_incrementing_string_primary_key(): void
    {
        $izin = Izin::factory()->create();

        $this->assertSame('uuid', $izin->getKeyName());
        $this->assertFalse($izin->getIncrementing());
        $this->assertSame('string', $izin->getKeyType());
        $this->assertNotEmpty($izin->uuid);
        $this->assertSame($izin->uuid, $izin->getKey());
        $this->assertTrue($izin->exists);
    }

    public function test_employee_and_both_approver_relationships_resolve_by_pegawai_uuid(): void
    {
        $pegawai = Pegawai::factory()->create(['nama' => 'Owner Employee']);
        $atasan = Pegawai::factory()->create(['nama' => 'Assigned Supervisor']);
        $pimpinan = Pegawai::factory()->create(['nama' => 'Assigned Executive']);
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
        ]);

        $this->assertTrue($izin->pegawai->is($pegawai));
        $this->assertTrue($izin->atasan_pimpinan->is($atasan));
        $this->assertTrue($izin->pimpinan->is($pimpinan));
    }

    public function test_nullable_approver_relationships_resolve_to_null(): void
    {
        $izin = Izin::factory()->create([
            'atasan_pimpinan_uuid' => null,
            'pimpinan_uuid' => null,
        ]);

        $this->assertNull($izin->atasan_pimpinan);
        $this->assertNull($izin->pimpinan);
    }

    public function test_request_and_verification_dates_are_exposed_as_carbon_dates(): void
    {
        $izin = Izin::factory()->create([
            'tanggal_mulai' => '2026-04-10',
            'tanggal_selesai' => '2026-04-12',
            'tanggal_verifikasi_atasan' => '2026-04-13',
            'tanggal_verifikasi_pimpinan' => '2026-04-14',
        ]);

        $this->assertSame('2026-04-10', $izin->tanggal_mulai->toDateString());
        $this->assertSame('2026-04-12', $izin->tanggal_selesai->toDateString());
        $this->assertSame('2026-04-13', $izin->tanggal_verifikasi_atasan->toDateString());
        $this->assertSame('2026-04-14', $izin->tanggal_verifikasi_pimpinan->toDateString());
    }
}
