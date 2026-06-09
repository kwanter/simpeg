<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPimpinanCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status_pimpinan' => ['required', 'in:Disetujui,Ditolak'],
            'catatan_pimpinan' => ['nullable', 'string'],
        ];
    }
}
