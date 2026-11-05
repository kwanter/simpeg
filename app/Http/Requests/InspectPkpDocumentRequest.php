<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InspectPkpDocumentRequest extends FormRequest
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
            'decision' => ['required', 'in:accept,return'],
            'reason' => ['required_if:decision,return', 'nullable', 'string', 'max:5000'],
            'matches_approved_content' => ['sometimes', 'boolean'],
            'actor_user_uuid' => ['prohibited'],
            'approved_submission_uuid' => ['prohibited'],
            'replacement_base_document_uuid' => ['prohibited'],
            'score' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'decided_at' => ['prohibited'],
        ];
    }
}
