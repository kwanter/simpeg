<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class IzinOwnershipAssignmentTest extends SimpegTestCase
{
    private function storePayload(Pegawai $atasan, Pegawai $pimpinan): array
    {
        return [
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'alasan' => 'Keperluan pribadi',
        ];
    }

    public function test_admin_can_create_izin_for_another_pegawai(): void
    {
        $admin = $this->createUserWithRole('admin');
        $adminPegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();
        $target = Pegawai::factory()->create();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($admin)->post(route('izin.store'), array_merge(
            $this->storePayload($atasan, $pimpinan),
            ['pegawai_uuid' => $target->uuid]
        ));

        $response->assertRedirect(route('izin.index'));
        $this->assertDatabaseHas('izin', ['pegawai_uuid' => $target->uuid]);
        $this->assertDatabaseMissing('izin', ['pegawai_uuid' => $adminPegawai->uuid]);
    }

    public function test_admin_without_pegawai_uuid_is_bound_to_own_pegawai(): void
    {
        $admin = $this->createUserWithRole('admin');
        $adminPegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($admin)->post(route('izin.store'), $this->storePayload($atasan, $pimpinan));

        $response->assertRedirect(route('izin.index'));
        $this->assertDatabaseHas('izin', ['pegawai_uuid' => $adminPegawai->uuid]);
    }

    public function test_admin_with_null_pegawai_uuid_is_bound_to_own_pegawai(): void
    {
        $admin = $this->createUserWithRole('admin');
        $adminPegawai = Pegawai::where('nip', $admin->nip)->firstOrFail();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($admin)->post(route('izin.store'), array_merge(
            $this->storePayload($atasan, $pimpinan),
            ['pegawai_uuid' => null]
        ));

        $response->assertRedirect(route('izin.index'));
        $this->assertDatabaseHas('izin', ['pegawai_uuid' => $adminPegawai->uuid]);
    }

    public function test_unknown_pegawai_uuid_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($admin)->post(route('izin.store'), array_merge(
            $this->storePayload($atasan, $pimpinan),
            ['pegawai_uuid' => (string) Str::uuid()]
        ));

        $response->assertSessionHasErrors('pegawai_uuid');
        $this->assertSame(0, Izin::count());
    }
}
