<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevisePkpRequest extends FormRequest
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
            'base_revision_uuid' => ['required', 'uuid'],
            'source' => ['required', 'in:application,external'],
            'pegawai_uuid' => ['prohibited'],
            'actor_user_uuid' => ['prohibited'],
            'official_revision_uuid' => ['prohibited'],
            'pending_revision_uuid' => ['prohibited'],
            'approved_submission_uuid' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'identity_snapshot' => ['prohibited'],
        ];
    }
}
