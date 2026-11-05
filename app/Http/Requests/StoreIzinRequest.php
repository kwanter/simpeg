<?php

namespace App\Http\Requests;

use App\Models\Izin;
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
        $rules = array_merge(
            ['pegawai_uuid' => ['nullable', 'exists:pegawai,uuid']],
            $this->baseRules()
        );

        return array_merge($rules, $this->jenisRules());
    }
}
