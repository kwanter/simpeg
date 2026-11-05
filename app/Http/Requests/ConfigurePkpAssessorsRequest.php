<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurePkpAssessorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'default_assessor_uuid' => ['nullable', 'uuid'],
            'allowed_assessor_uuids' => ['required', 'array', 'min:1'],
            'allowed_assessor_uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
