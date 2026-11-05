<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePkpRequest extends FormRequest
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
            'year' => ['required', 'integer', 'between:1900,9999'],
            'month' => ['required', 'integer', 'between:1,12'],
            'source' => ['required', 'in:application,external'],
            'assessor_pegawai_uuid' => ['nullable', 'uuid'],
            'pegawai_uuid' => ['prohibited'],
            'actor_user_uuid' => ['prohibited'],
            'version' => ['prohibited'],
            'state' => ['prohibited'],
            'score' => ['prohibited'],
            'snapshot' => ['prohibited'],
            'indicators' => ['prohibited'],
            'score' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'calculation_version' => ['prohibited'],
            'lateness' => ['prohibited'],
            'identity_snapshot' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['_token', '_method', 'year', 'month', 'source', 'assessor_pegawai_uuid'];
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true) && ! array_key_exists($key, $this->rules())) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }
        });
    }
}
