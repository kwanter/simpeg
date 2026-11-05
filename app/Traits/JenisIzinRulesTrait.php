<?php

namespace App\Traits;

use App\Support\IzinType;

trait JenisIzinRulesTrait
{
    /**
     * The rule set both Izin requests share. Each request merges its own delta
     * on top: StoreIzinRequest prepends pegawai_uuid, while UpdateIzinRequest
     * short-circuits to no_surat_izin for an approved Izin (ARCH-01).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function baseRules(): array
    {
        return [
            'jenis_izin' => ['required', 'string', 'in:'.implode(',', IzinType::all())],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
            'alasan' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:2048'],
            'atasan_pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
            'pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
        ];
    }

    /**
     * Indonesian messages for the jenis-specific rules below. Rules without an
     * entry here fall back to Laravel's default message.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $jenis = $this->jenis();

        if ($jenis !== null && IzinType::isSingleLevel($jenis)) {
            return [
                'tanggal_mulai.date_equals' => 'Izin keluar kantor hanya dapat diajukan pada hari ini.',
                'tanggal_selesai.date_equals' => 'Izin keluar kantor hanya dapat diajukan pada hari ini.',
                'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
            ];
        }

        if ($jenis === IzinType::TIDAK_MASUK) {
            return [
                'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            ];
        }

        return [];
    }

    /**
     * Rules layered on top of baseRules() for one specific Jenis Izin.
     *
     * @return array<string, array<int, mixed>>
     */
    private function jenisRules(): array
    {
        $jenis = $this->jenis();

        if ($jenis !== null && IzinType::isSingleLevel($jenis)) {
            // Izin Keluar Kantor and Izin Pulang Cepat - Pasal 5, PERMA No. 7/2016, Lampiran II
            return [
                'tanggal_mulai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
                'tanggal_selesai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
                'jam_mulai' => ['required', 'date_format:H:i'],
                'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
                'alasan' => ['required', 'string', 'max:500'],
            ];
        }

        if ($jenis === IzinType::TIDAK_MASUK) {
            // Izin Tidak Masuk Kerja - Pasal 8, PERMA No. 7/2016, Lampiran III, capped at 2 working days
            return [
                'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
                'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
                'alasan' => ['required', 'string', 'max:500'],
            ];
        }

        return [];
    }

    private function jenis(): ?string
    {
        $jenis = $this->input('jenis_izin');

        return is_string($jenis) ? $jenis : null;
    }
}
