<?php

namespace Tests\Unit\Services;

use App\Models\Izin;
use App\Services\IzinApprovalService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\SimpegTestCase;

class IzinApprovalServiceTest extends SimpegTestCase
{
    private IzinApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IzinApprovalService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_atasan_unrecognized_decision_fails_database_constraint_and_rolls_back(): void
    {
        $izin = Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Diajukan',
        ]);

        try {
            $this->service->applyAtasan($izin, 'Ditunda', null);
            $this->fail('The database constraint should reject an unsupported decision.');
        } catch (QueryException) {
            $persisted = Izin::where('uuid', $izin->uuid)->firstOrFail();
            $this->assertSame('Diajukan', $persisted->status);
            $this->assertSame('Belum Diverifikasi', $persisted->verifikasi_atasan);
        }
    }

    public function test_pimpinan_unrecognized_decision_fails_database_constraint_and_rolls_back(): void
    {
        $izin = Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Disetujui Atasan',
        ]);

        try {
            $this->service->applyPimpinan($izin, 'Ditunda', null);
            $this->fail('The database constraint should reject an unsupported decision.');
        } catch (QueryException) {
            $persisted = Izin::where('uuid', $izin->uuid)->firstOrFail();
            $this->assertSame('Disetujui Atasan', $persisted->status);
            $this->assertSame('Belum Diverifikasi', $persisted->verifikasi_pimpinan);
        }
    }

    public function test_approval_timestamps_are_persisted_as_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-17 14:35:42'));
        $izin = Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Diajukan',
        ]);

        $result = $this->service->applyAtasan($izin, 'Disetujui', null);

        $this->assertSame('2026-04-17', $result->tanggal_verifikasi_atasan->toDateString());
        $this->assertSame('date', $result->getCasts()['tanggal_verifikasi_atasan']);
    }

    public function test_wrong_status_returns_the_current_validation_error_payload(): void
    {
        $izin = Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Ditolak',
        ]);

        try {
            $this->service->applyAtasan($izin, 'Disetujui', 'retry');
            $this->fail('A request with an unexpected status should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'status' => ['Permohonan izin sudah diproses atau berada pada status yang tidak valid.'],
            ], $exception->errors());
        }

        $persisted = Izin::where('uuid', $izin->uuid)->firstOrFail();
        $this->assertSame('Ditolak', $persisted->status);
        $this->assertSame('Belum Diverifikasi', $persisted->verifikasi_atasan);
    }
}
