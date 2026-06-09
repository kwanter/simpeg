<?php

namespace Tests\Unit\Services;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Services\IzinQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class IzinQueryServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    public function test_admin_scope_returns_all_izin(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $pegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();
        Izin::factory()->count(3)->create(['pegawai_uuid' => $pegawai->uuid]);

        $result = (new IzinQueryService)->forUser($admin);

        $this->assertSame(3, $result->count());
    }

    public function test_atasan_pimpinan_scope_filters_by_atasan_pimpinan_uuid(): void
    {
        $ats = $this->createUserWithRole('atasan-pimpinan');
        $other = $this->createUserWithRole('user');

        $atsPegawai = Pegawai::where('nip', $ats->nip)->firstOrFail();
        $otherPegawai = Pegawai::where('nip', $other->nip)->firstOrFail();

        Izin::factory()->count(2)->create(['atasan_pimpinan_uuid' => $atsPegawai->uuid]);
        Izin::factory()->count(3)->create(['atasan_pimpinan_uuid' => $otherPegawai->uuid]);

        $result = (new IzinQueryService)->forUser($ats);

        $this->assertSame(2, $result->count());
    }

    public function test_pimpinan_scope_filters_by_pimpinan_uuid(): void
    {
        $pim = $this->createUserWithRole('pimpinan');
        $other = $this->createUserWithRole('user');

        $pimPegawai = Pegawai::where('nip', $pim->nip)->firstOrFail();
        $otherPegawai = Pegawai::where('nip', $other->nip)->firstOrFail();

        Izin::factory()->count(2)->create(['pimpinan_uuid' => $pimPegawai->uuid]);
        Izin::factory()->count(3)->create(['pimpinan_uuid' => $otherPegawai->uuid]);

        $result = (new IzinQueryService)->forUser($pim);

        $this->assertSame(2, $result->count());
    }

    public function test_regular_user_scope_filters_by_pegawai_uuid(): void
    {
        $u = $this->createUserWithRole('user');
        $other = $this->createUserWithRole('user');

        $uPegawai = Pegawai::where('nip', $u->nip)->firstOrFail();
        $otherPegawai = Pegawai::where('nip', $other->nip)->firstOrFail();

        Izin::factory()->count(1)->create(['pegawai_uuid' => $uPegawai->uuid]);
        Izin::factory()->count(4)->create(['pegawai_uuid' => $otherPegawai->uuid]);

        $result = (new IzinQueryService)->forUser($u);

        $this->assertSame(1, $result->count());
    }

    public function test_user_without_pegawai_gets_empty_collection(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $user = $this->createUserWithRole('user');
        Pegawai::where('nip', $user->nip)->delete();

        $result = (new IzinQueryService)->forUser($user);

        $this->assertSame(0, $result->count());
    }

    public function test_admin_scope_can_filter_by_single_jenis(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $pegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();

        Izin::factory()->count(2)->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Keluar Kantor',
        ]);
        Izin::factory()->count(3)->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
        ]);

        $result = (new IzinQueryService)->forUser($admin, ['Izin Keluar Kantor']);

        $this->assertSame(2, $result->count());
    }

    public function test_admin_scope_can_filter_by_multiple_jenis(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $pegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();

        Izin::factory()->count(2)->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Keluar Kantor',
        ]);
        Izin::factory()->count(3)->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Tidak Masuk Kerja',
        ]);
        Izin::factory()->count(4)->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Sakit',
        ]);

        $result = (new IzinQueryService)->forUser($admin, [
            'Izin Keluar Kantor',
            'Izin Tidak Masuk Kerja',
        ]);

        $this->assertSame(5, $result->count());
    }
}
