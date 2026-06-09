<?php

namespace App\Services;

use App\Models\Cuti;
use App\Models\Pegawai;
use Carbon\Carbon;

class CutiEligibilityService
{
    /**
     * Active cuti statuses that block new applications of the opposite leave type.
     *
     * @var list<string>
     */
    private const ACTIVE_STATUSES = [
        'Pending',
        'Disetujui Verifikator',
        'Disetujui Pimpinan',
        'Disetujui Atasan Pimpinan',
    ];

    public function __construct(private readonly CutiBalanceService $balances) {}

    public function checkAnnualLeave(string $pegawaiUuid, Carbon $start, Carbon $end, ?string $excludeUuid = null): ?string
    {
        $year = (int) $start->format('Y');
        $lamaCuti = WorkdayService::countWorkdays($start, $end);
        $remaining = $this->balances->remainingDays($pegawaiUuid, $year);

        if ($this->hasActiveCutiOfType($pegawaiUuid, 'Cuti Besar', $year, $excludeUuid)) {
            return 'Anda sudah mengajukan Cuti Besar di tahun ini, sehingga tidak dapat mengajukan Cuti Tahunan.';
        }

        if ($lamaCuti > $remaining) {
            return "Sisa cuti tahunan Anda tidak mencukupi. Sisa cuti: {$remaining} hari, permintaan: {$lamaCuti} hari.";
        }

        return null;
    }

    public function checkCutiBesar(Pegawai $pegawai, Carbon $start, Carbon $end, ?string $excludeUuid = null): ?string
    {
        if (! $pegawai->tanggal_masuk) {
            return 'Tanggal masuk kerja pegawai tidak ditemukan untuk validasi Cuti Besar.';
        }

        $masaKerja = Carbon::now()->diffInYears(Carbon::parse($pegawai->tanggal_masuk));
        if ($masaKerja < 5) {
            return 'Cuti Besar hanya dapat diajukan oleh pegawai dengan masa kerja minimal 5 tahun.';
        }

        $lamaCuti = WorkdayService::countWorkdays($start, $end);
        if ($lamaCuti > 90) {
            return "Cuti Besar maksimal 3 bulan (sekitar 90 hari kerja). Permintaan Anda: {$lamaCuti} hari.";
        }

        $year = (int) $start->format('Y');
        if ($this->hasActiveCutiOfType($pegawai->uuid, 'Cuti Tahunan', $year, $excludeUuid)) {
            return 'Anda sudah mengajukan Cuti Tahunan di tahun ini, sehingga tidak dapat mengajukan Cuti Besar.';
        }

        if ($this->hasActiveCutiOfType($pegawai->uuid, 'Cuti Besar', $year, $excludeUuid)) {
            return 'Anda sudah mengajukan Cuti Besar di tahun ini.';
        }

        return null;
    }

    private function hasActiveCutiOfType(string $pegawaiUuid, string $jenis, int $year, ?string $excludeUuid = null): bool
    {
        return Cuti::where('pegawai_uuid', $pegawaiUuid)
            ->where('jenis_cuti', $jenis)
            ->whereYear('tanggal_mulai', $year)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($excludeUuid, fn ($q) => $q->where('uuid', '!=', $excludeUuid))
            ->exists();
    }
}
