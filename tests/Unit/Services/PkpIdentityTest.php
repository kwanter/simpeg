<?php

namespace Tests\Unit\Services;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Services\PkpIdentityService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\SimpegTestCase;

class PkpIdentityTest extends SimpegTestCase
{
    public function test_resolves_history_effective_at_month_end_and_retains_source_ids(): void
    {
        $pegawai = Pegawai::factory()->create();
        $this->addRankHistory($pegawai, 'III/a', '2029-01-01');
        $effectiveRank = $this->addRankHistory($pegawai, 'IV/a', '2030-02-01');
        $this->addRankHistory($pegawai, 'V/a', '2030-03-01');

        $oldJabatan = Jabatan::factory()->create(['nama' => 'Hakim Pratama']);
        $currentJabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
        $this->addPositionHistory($pegawai, $oldJabatan, 'PN Lama', '2029-01-01');
        $effectivePosition = $this->addPositionHistory($pegawai, $currentJabatan, 'PN Baru', '2030-02-01');
        $this->addPositionHistory($pegawai, Jabatan::factory()->create(['nama' => 'Hakim Utama']), 'PN Berikut', '2030-03-01');

        $identity = app(PkpIdentityService::class)->resolve($pegawai, 2030, 2);

        $this->assertSame('2030-02-28', $identity['period_end']);
        $this->assertSame('IV/a', $identity['rank']['value']);
        $this->assertSame([$effectiveRank->uuid], $identity['rank']['history_uuids']);
        $this->assertSame('Hakim Madya', $identity['position']['value']);
        $this->assertSame([$effectivePosition->uuid], $identity['position']['history_uuids']);
        $this->assertSame('PN Baru', $identity['work_unit']['value']);
        $this->assertSame($pegawai->nama, $identity['employee']['name']);
        $this->assertSame($pegawai->nip, $identity['employee']['nip']);
    }

    public function test_identical_latest_history_rows_are_retained_together(): void
    {
        $pegawai = Pegawai::factory()->create();
        $first = $this->addRankHistory($pegawai, 'IV/a', '2030-02-01');
        $second = $this->addRankHistory($pegawai, 'IV/a', '2030-02-01');
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
        $this->addPositionHistory($pegawai, $jabatan, 'PN Baru', '2030-02-01');

        $identity = app(PkpIdentityService::class)->resolve($pegawai, 2030, 2);

        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], $identity['rank']['history_uuids']);
    }

    public function test_missing_or_conflicting_effective_histories_are_rejected(): void
    {
        $pegawai = Pegawai::factory()->create();
        $jabatan = Jabatan::factory()->create(['nama' => 'Hakim Madya']);
        $this->addPositionHistory($pegawai, $jabatan, 'PN Baru', '2030-02-01');

        try {
            app(PkpIdentityService::class)->resolve($pegawai, 2030, 2);
            $this->fail('Missing rank history must prevent submission identity resolution.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $this->addRankHistory($pegawai, 'IV/a', '2030-02-01');
        $this->addPositionHistory($pegawai, Jabatan::factory()->create(['nama' => 'Hakim Utama']), 'PN Berbeda', '2030-02-01');

        try {
            app(PkpIdentityService::class)->resolve($pegawai, 2030, 2);
            $this->fail('Conflicting latest position history must not be selected arbitrarily.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function addRankHistory(Pegawai $pegawai, string $rank, string $effective): RiwayatPangkat
    {
        return RiwayatPangkat::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'pangkat_golongan' => $rank,
            'tmt' => $effective,
            'nomor_sk' => 'SK-'.$effective,
            'tanggal_sk' => $effective,
        ]);
    }

    private function addPositionHistory(Pegawai $pegawai, Jabatan $jabatan, string $workUnit, string $effective): RiwayatJabatan
    {
        return RiwayatJabatan::query()->create([
            'uuid' => (string) Str::uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'jabatan_uuid' => $jabatan->uuid,
            'satuan_kerja' => $workUnit,
            'tanggal_mulai' => $effective,
        ]);
    }
}
