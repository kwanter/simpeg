<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAtasanIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy checks stay in controller via $this->authorize()
    }

    public function rules(): array
    {
        return [
            'verifikasi_atasan' => ['required', 'in:Disetujui,Ditolak'],
            'catatan_atasan' => ['nullable', 'string'],
        ];
    }
}
