<?php

namespace App\Http\Requests;

use App\Models\Izin;
use App\Support\IzinType;
use App\Traits\JenisIzinRulesTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIzinRequest extends FormRequest
{
    use JenisIzinRulesTrait;

    public function authorize(): bool
    {
        $izin = Izin::where('uuid', $this->route('izin'))->firstOrFail();

        return $this->user()->can('update', $izin);
    }

    public function rules(): array
    {
        // Check if this is just a no_surat_izin update for an approved izin
        if ($this->isNoSuratUpdate()) {
            return [
                'no_surat_izin' => ['required', 'string', Rule::unique('izin', 'no_surat_izin')->ignore($this->route('izin'), 'uuid')],
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

    /**
     * Check if this is just a no_surat_izin update for an approved izin
     * (csrf, method, and no_surat_izin only).
     */
    public function isNoSuratUpdate(): bool
    {
        $izin = Izin::where('uuid', $this->route('izin'))->first();

        return $izin !== null
            && $izin->verifikasi_atasan == 'Disetujui'
            && $this->has('no_surat_izin')
            && count($this->all()) <= 3;
    }
}
