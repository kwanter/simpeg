<?php

namespace App\Services;

use App\Models\Izin;
use App\Support\IzinType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IzinApprovalService
{
    public const APPROVE = 'Disetujui';

    public const REJECT = 'Ditolak';

    public function applyAtasan(Izin $izin, string $decision, ?string $catatan): Izin
    {
        return DB::transaction(function () use ($izin, $decision, $catatan) {
            $izin = $this->lockAtStatus($izin, 'Diajukan');
            $izin->verifikasi_atasan = $decision;
            $izin->catatan_atasan = $catatan;
            $izin->tanggal_verifikasi_atasan = Carbon::now();

            if ($decision === self::APPROVE) {
                // Single-level jenis (Pasal 5 PERMA No. 7/2016): atasan approval is final.
                $izin->status = IzinType::isSingleLevel($izin->jenis_izin) ? 'Disetujui' : 'Disetujui Atasan';
            } else {
                $izin->status = 'Ditolak Atasan';
            }

            $izin->save();

            return $izin;
        });
    }

    public function applyPimpinan(Izin $izin, string $decision, ?string $catatan): Izin
    {
        return DB::transaction(function () use ($izin, $decision, $catatan) {
            $izin = $this->lockAtStatus($izin, 'Disetujui Atasan');
            $izin->verifikasi_pimpinan = $decision;
            $izin->catatan_pimpinan = $catatan;
            $izin->tanggal_verifikasi_pimpinan = Carbon::now();
            $izin->status = $decision === self::APPROVE ? 'Disetujui' : 'Ditolak';
            $izin->save();

            return $izin;
        });
    }

    private function lockAtStatus(Izin $izin, string $expected): Izin
    {
        $locked = Izin::where('uuid', $izin->uuid)->lockForUpdate()->firstOrFail();
        if ($locked->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ['Permohonan izin sudah diproses atau berada pada status yang tidak valid.'],
            ]);
        }

        return $locked;
    }
}
