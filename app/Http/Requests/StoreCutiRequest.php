<?php

namespace App\Http\Requests;

use App\Support\CutiType;
use Illuminate\Foundation\Http\FormRequest;

class StoreCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Cuti::class);
    }

    public function rules(): array
    {
        return [
            'jenis_cuti' => ['required', 'string', 'in:'.implode(',', CutiType::all())],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string'],
            'alamat_selama_cuti' => ['required', 'string'],
            'no_hp_selama_cuti' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:2048'],
            'pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
            'atasan_pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
        ];
    }
}
