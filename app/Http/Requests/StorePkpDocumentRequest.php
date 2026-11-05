<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePkpDocumentRequest extends FormRequest
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
            'kind' => ['required', 'in:source,signed_final'],
            'file' => ['required', 'file', 'max:10240'],
            'declared_signed' => $this->input('kind') === 'signed_final'
                ? ['prohibited']
                : ['sometimes', 'boolean'],
            'content_unchanged' => $this->input('kind') === 'signed_final'
                ? ['sometimes', 'boolean']
                : ['prohibited'],
            'pegawai_uuid' => ['prohibited'],
            'actor_user_uuid' => ['prohibited'],
            'revision_uuid' => ['prohibited'],
            'storage_path' => ['prohibited'],
            'replacement_of_document_uuid' => ['prohibited'],
            'sha256' => ['prohibited'],
            'page_count' => ['prohibited'],
            'detected_mime' => ['prohibited'],
        ];
    }
}
