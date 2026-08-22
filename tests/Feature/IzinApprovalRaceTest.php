<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Services\IzinApprovalService;
use Illuminate\Validation\ValidationException;
use Tests\SimpegTestCase;

class IzinApprovalRaceTest extends SimpegTestCase
{
    private IzinApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IzinApprovalService;
    }

    private function makeIzin(array $attrs = []): Izin
    {
        return Izin::factory()->create(array_merge([
            'jenis_izin' => 'Izin Keperluan Pribadi', // two-level
            'status' => 'Diajukan',
        ], $attrs));
    }

    public function test_atasan_approval_on_diajukan_two_level_sets_disetujui_atasan(): void
    {
        $izin = $this->makeIzin();
        $result = $this->service->applyAtasan($izin, 'Disetujui', 'ok');

        $this->assertSame('Disetujui Atasan', $result->status);
        $this->assertSame('Disetujui', $result->verifikasi_atasan);
        $this->assertSame('ok', $result->catatan_atasan);
        $this->assertNotNull($result->tanggal_verifikasi_atasan);
    }

    public function test_atasan_approval_on_single_level_jenis_is_final(): void
    {
        $izin = $this->makeIzin(['jenis_izin' => 'Izin Keluar Kantor']);
        $result = $this->service->applyAtasan($izin, 'Disetujui', 'ok');

        $this->assertSame('Disetujui', $result->status);
    }

    public function test_duplicate_atasan_approval_throws_and_keeps_state(): void
    {
        $izin = $this->makeIzin();
        $this->service->applyAtasan($izin, 'Disetujui', 'ok');

        try {
            $this->service->applyAtasan($izin, 'Ditolak', 'again');
            $this->fail('Duplicate approval must fail.');
        } catch (ValidationException) {
            $fresh = Izin::where('uuid', $izin->uuid)->firstOrFail();
            $this->assertSame('Disetujui Atasan', $fresh->status);
            $this->assertSame('Disetujui', $fresh->verifikasi_atasan);
            $this->assertSame('ok', $fresh->catatan_atasan);
        }
    }

    public function test_pimpinan_approval_on_diajukan_throws(): void
    {
        $izin = $this->makeIzin(['status' => 'Diajukan']);
        $this->expectException(ValidationException::class);
        $this->service->applyPimpinan($izin, 'Disetujui', 'skip');
    }

    public function test_pimpinan_approval_on_disetujui_atasan_succeeds(): void
    {
        $izin = $this->makeIzin(['status' => 'Disetujui Atasan']);
        $result = $this->service->applyPimpinan($izin, 'Disetujui', 'lgtm');

        $this->assertSame('Disetujui', $result->status);
        $this->assertSame('Disetujui', $result->verifikasi_pimpinan);
        $this->assertSame('lgtm', $result->catatan_pimpinan);
        $this->assertNotNull($result->tanggal_verifikasi_pimpinan);
    }

    public function test_atasan_rejection_sets_ditolak_atasan(): void
    {
        $izin = $this->makeIzin();
        $result = $this->service->applyAtasan($izin, 'Ditolak', 'no');

        $this->assertSame('Ditolak Atasan', $result->status);
        $this->assertSame('Ditolak', $result->verifikasi_atasan);
    }

    public function test_pimpinan_rejection_sets_ditolak(): void
    {
        $izin = $this->makeIzin(['status' => 'Disetujui Atasan']);
        $result = $this->service->applyPimpinan($izin, 'Ditolak', 'no');

        $this->assertSame('Ditolak', $result->status);
        $this->assertSame('Ditolak', $result->verifikasi_pimpinan);
    }
}
