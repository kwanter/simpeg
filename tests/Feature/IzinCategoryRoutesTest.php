<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Support\IzinType;
use Tests\SimpegTestCase;

class IzinCategoryRoutesTest extends SimpegTestCase
{
    public function test_keluar_kantor_index_only_displays_single_level_izin_types_for_the_owner(): void
    {
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $keluarKantor = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::KELUAR_KANTOR,
        ]);
        $pulangCepat = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::PULANG_CEPAT,
        ]);
        Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::SAKIT,
        ]);
        Izin::factory()->create([
            'jenis_izin' => IzinType::KELUAR_KANTOR,
        ]);

        $response = $this->actingAs($user)->get(route('izin.index-keluar-kantor'));

        $response->assertOk()->assertViewIs('izin.index-keluar-kantor');
        $response->assertViewHas('izins', function ($izins) use ($keluarKantor, $pulangCepat): bool {
            return $izins->pluck('uuid')->sort()->values()->all() === collect([
                $keluarKantor->uuid,
                $pulangCepat->uuid,
            ])->sort()->values()->all();
        });
    }

    public function test_tidak_masuk_index_only_displays_tidak_masuk_izin_for_the_owner(): void
    {
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::TIDAK_MASUK,
        ]);
        Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::SAKIT,
        ]);
        Izin::factory()->create([
            'jenis_izin' => IzinType::TIDAK_MASUK,
        ]);

        $response = $this->actingAs($user)->get(route('izin.index-tidak-masuk'));

        $response->assertOk()->assertViewIs('izin.index-tidak-masuk');
        $response->assertViewHas('izins', function ($izins) use ($izin): bool {
            return $izins->pluck('uuid')->all() === [$izin->uuid];
        });
    }
}
