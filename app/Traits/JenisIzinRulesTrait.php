<?php

namespace App\Traits;

use App\Support\IzinType;

trait JenisIzinRulesTrait
{
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
     * Validasi khusus untuk Izin Keluar Kantor dan Izin Pulang Cepat.
     * Pasal 5 PERMA No. 7 Tahun 2016 - Lampiran II
     * Validasi khusus untuk Izin Tidak Masuk Kerja.
     * Pasal 8 PERMA No. 7 Tahun 2016 - Lampiran III - Maks 2 hari kerja
     */
    private function jenisRules(): array
    {
        $jenis = $this->jenis();

        if ($jenis !== null && IzinType::isSingleLevel($jenis)) {
            return [
                'tanggal_mulai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
                'tanggal_selesai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
                'jam_mulai' => ['required', 'date_format:H:i'],
                'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
                'alasan' => ['required', 'string', 'max:500'],
            ];
        }

        if ($jenis === IzinType::TIDAK_MASUK) {
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
