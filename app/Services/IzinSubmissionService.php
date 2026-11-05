<?php

namespace App\Services;

use App\Models\User;
use App\Support\IzinType;
use Illuminate\Validation\ValidationException;

class IzinSubmissionService
{
    public function resolveOwnerUuid(User $actor, ?string $requestedPegawaiUuid): string
    {
        if ($actor->hasAnyRole(['super-admin', 'admin']) && ! empty($requestedPegawaiUuid)) {
            return $requestedPegawaiUuid;
        }

        return $actor->pegawai()->firstOrFail()->uuid;
    }

    public function prepareForCreate(array $validated): array
    {
        return array_merge($this->normalizeJenis($validated), [
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);
    }

    public function prepareForFullUpdate(array $validated): array
    {
        $approvalMetadata = [
            'status',
            'verifikasi_atasan',
            'verifikasi_pimpinan',
            'tanggal_verifikasi_atasan',
            'tanggal_verifikasi_pimpinan',
            'no_surat_izin',
        ];

        return $this->normalizeJenis(array_diff_key($validated, array_flip($approvalMetadata)));
    }

    private function normalizeJenis(array $validated): array
    {
        $jenis = $validated['jenis_izin'];

        if (IzinType::isSingleLevel($jenis)) {
            $validated['jumlah_hari'] = 0;
            $validated['tanggal_mulai'] = now()->toDateString();
            $validated['tanggal_selesai'] = now()->toDateString();

            return $validated;
        }

        $validated['jumlah_hari'] = WorkdayService::countWorkdays(
            $validated['tanggal_mulai'],
            $validated['tanggal_selesai']
        );

        if ($jenis === IzinType::TIDAK_MASUK
            && $validated['jumlah_hari'] > IzinType::maxWorkdays(IzinType::TIDAK_MASUK)) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
            ]);
        }

        return $validated;
    }
}
