<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPkpRequest extends FormRequest
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
            'pegawai_uuid' => ['prohibited'],
            'actor_user_uuid' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'identity_snapshot' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'lateness' => ['prohibited'],
            'state' => ['prohibited'],
            'decision' => ['prohibited'],
        ];
    }
}
