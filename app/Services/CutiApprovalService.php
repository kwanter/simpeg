<?php

namespace App\Services;

use App\Models\Cuti;
use App\Models\Pegawai;
use Carbon\Carbon;

class CutiApprovalService
{
    public const ANNUAL_LEAVE = 'Cuti Tahunan';

    public const APPROVE = 'Disetujui';

    public const REJECT = 'Ditolak';

    public function __construct(private readonly CutiBalanceService $balances) {}

    public function applyVerifikator(Cuti $cuti, string $decision, ?string $catatan, ?Pegawai $verifikator = null): Cuti
    {
        $newStatus = $decision === self::APPROVE ? 'Disetujui Verifikator' : 'Ditolak Verifikator';

        $cuti->status = $newStatus;
        $cuti->status_verifikator = $decision;
        $cuti->catatan_verifikator = $catatan;
        if ($verifikator) {
            $cuti->verifikator_uuid = $verifikator->uuid;
        }
        $cuti->tanggal_verifikasi = Carbon::now();
        $cuti->save();

        return $cuti;
    }

    public function applyPimpinan(Cuti $cuti, Pegawai $pimpinans, string $decision, ?string $catatan): Cuti
    {
        $newStatus = $decision === self::APPROVE ? 'Disetujui Pimpinan' : 'Ditolak Pimpinan';

        $cuti->status = $newStatus;
        $cuti->status_pimpinan = $decision;
        $cuti->catatan_pimpinan = $catatan;
        $cuti->pimpinan_uuid = $pimpinans->uuid;
        $cuti->tanggal_verifikasi_pimpinan = Carbon::now();
        $cuti->save();

        if ($newStatus === 'Disetujui Pimpinan' && $cuti->jenis_cuti === self::ANNUAL_LEAVE) {
            $year = (int) Carbon::parse($cuti->tanggal_mulai)->format('Y');
            $workdays = WorkdayService::countWorkdays($cuti->tanggal_mulai, $cuti->tanggal_selesai);
            $this->balances->deductWorkdays($cuti->pegawai_uuid, $year, $workdays);
        }

        return $cuti;
    }

    public function applyAtasanPimpinan(Cuti $cuti, Pegawai $atasan, string $decision, ?string $catatan): Cuti
    {
        $newStatus = $decision === self::APPROVE ? 'Disetujui Atasan Pimpinan' : 'Ditolak Atasan Pimpinan';

        $cuti->status = $newStatus;
        $cuti->status_atasan_pimpinan = $decision;
        $cuti->catatan_atasan_pimpinan = $catatan;
        $cuti->atasan_pimpinan_uuid = $atasan->uuid;
        $cuti->tanggal_verifikasi_atasan_pimpinan = Carbon::now();
        $cuti->save();

        if ($newStatus === 'Ditolak Atasan Pimpinan' && $cuti->jenis_cuti === self::ANNUAL_LEAVE) {
            $year = (int) Carbon::parse($cuti->tanggal_mulai)->format('Y');
            $workdays = WorkdayService::countWorkdays($cuti->tanggal_mulai, $cuti->tanggal_selesai);
            $this->balances->refundWorkdays($cuti->pegawai_uuid, $year, $workdays);
        }

        return $cuti;
    }
}
