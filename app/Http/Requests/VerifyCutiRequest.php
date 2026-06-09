<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCutiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via policy
    }

    public function rules(): array
    {
        return [
            'status_verifikator' => ['required', 'in:Disetujui,Ditolak'],
            'catatan_verifikator' => ['nullable', 'string'],
        ];
    }
}
