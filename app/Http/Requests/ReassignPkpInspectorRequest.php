<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReassignPkpInspectorRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'revision_uuid' => ['required', 'uuid'],
            'inspector_pegawai_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
