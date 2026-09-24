<?php

namespace App\Http\Requests;

use App\Models\Izin;
use App\Support\IzinType;
use App\Traits\JenisIzinRulesTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreIzinRequest extends FormRequest
{
    use JenisIzinRulesTrait;

    public function authorize(): bool
    {
        return $this->user()->can('create', Izin::class);
    }

    public function rules(): array
    {
        $rules = [
            'pegawai_uuid' => ['nullable', 'exists:pegawai,uuid'],
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
}
