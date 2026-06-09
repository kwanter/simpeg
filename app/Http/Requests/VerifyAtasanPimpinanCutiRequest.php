<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAtasanPimpinanCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_atasan_pimpinan' => ['required', 'in:Disetujui,Ditolak'],
            'catatan_atasan_pimpinan' => ['nullable', 'string'],
        ];
    }
}
