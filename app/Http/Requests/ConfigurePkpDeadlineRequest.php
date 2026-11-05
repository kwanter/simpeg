<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigurePkpDeadlineRequest extends FormRequest
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
            'deadline_local' => ['present', 'nullable', 'string', 'date_format:Y-m-d\TH:i:s'],
            'actor_user_uuid' => ['prohibited'],
            'year' => ['prohibited'],
            'month' => ['prohibited'],
            'deadline_at' => ['prohibited'],
            'version' => ['prohibited'],
        ];
    }
}
