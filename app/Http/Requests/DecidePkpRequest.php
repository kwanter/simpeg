<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecidePkpRequest extends FormRequest
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
            'submission_uuid' => ['required', 'uuid'],
            'decision' => ['required', 'in:approve,return'],
            'reason' => ['required_if:decision,return', 'nullable', 'string', 'max:5000'],
            'actor_user_uuid' => ['prohibited'],
            'score' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'identity_snapshot' => ['prohibited'],
            'decided_at' => ['prohibited'],
        ];
    }
}
