<?php

namespace App\Http\Requests;

use App\Models\Izin;
use App\Support\IzinType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checks stay in controller via $this->authorize()
    }

    public function rules(): array
    {
        // Check if this is just a no_surat_izin update for an approved izin
        if ($this->isNoSuratUpdate()) {
            return [
                'no_surat_izin' => ['required', 'string', Rule::unique('izin', 'no_surat_izin')->ignore($this->route('uuid'), 'uuid')],
            ];
        }

        $rules = [
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

        return array_merge($rules, $this->jenisRules());
    }

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
     * Check if this is just a no_surat_izin update for an approved izin
     * (csrf, method, and no_surat_izin only).
     */
    private function isNoSuratUpdate(): bool
    {
        $izin = Izin::where('uuid', $this->route('uuid'))->first();

        return $izin !== null
            && $izin->verifikasi_atasan == 'Disetujui'
            && $this->has('no_surat_izin')
            && count($this->all()) <= 3;
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
