<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePkpRequest extends FormRequest
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
        $decimal = ['sometimes', 'nullable', 'string', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/'];

        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessor_pegawai_uuid' => ['sometimes', 'uuid'],
            'indicators' => ['sometimes', 'array', 'max:50'],
            'indicators.*' => ['required', 'array:title,activities'],
            'indicators.*.title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'indicators.*.activities' => ['required', 'array'],
            'indicators.*.activities.*' => ['required', 'array:description,unit,target_credit,target_quantity,target_quality,realized_quantity'],
            'indicators.*.activities.*.description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'indicators.*.activities.*.unit' => ['sometimes', 'nullable', 'string', 'max:100'],
            'indicators.*.activities.*.target_credit' => $decimal,
            'indicators.*.activities.*.target_quantity' => $decimal,
            'indicators.*.activities.*.target_quality' => $decimal,
            'indicators.*.activities.*.realized_quantity' => $decimal,
            'pegawai_uuid' => ['prohibited'],
            'actor_user_uuid' => ['prohibited'],
            'source' => ['prohibited'],
            'year' => ['prohibited'],
            'month' => ['prohibited'],
            'state' => ['prohibited'],
            'score' => ['prohibited'],
            'score_snapshot' => ['prohibited'],
            'snapshot' => ['prohibited'],
            'target_unit' => ['prohibited'],
            'realized_unit' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['expected_version', 'indicators'];
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true) && ! array_key_exists($key, $this->rules())) {
                    $validator->errors()->add($key, 'This field is not allowed.');
                }
            }

            $indicators = $this->input('indicators', []);
            $activityCount = 0;
            foreach (is_array($indicators) ? $indicators : [] as $indicatorIndex => $indicator) {
                $activities = is_array($indicator) ? ($indicator['activities'] ?? []) : [];
                $activityCount += is_array($activities) ? count($activities) : 0;

                foreach (is_array($activities) ? $activities : [] as $activityIndex => $activity) {
                    if (! is_array($activity)) {
                        continue;
                    }

                    foreach ([
                        'target_credit' => [6, '99999999.999999'],
                        'target_quantity' => [4, '9999999999.9999'],
                        'realized_quantity' => [4, '9999999999.9999'],
                        'target_quality' => [4, '100.0000'],
                    ] as $field => [$scale, $maximum]) {
                        $value = $activity[$field] ?? null;
                        if (is_string($value) && $value !== trim($value)) {
                            $validator->errors()->add("indicators.{$indicatorIndex}.activities.{$activityIndex}.{$field}", 'Numeric values must not contain surrounding whitespace.');

                            continue;
                        }
                        if ($value === null || ! is_string($value) || ! preg_match('/^(?:0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $value, $matches)) {
                            continue;
                        }

                        $fraction = $matches[1] ?? '';
                        $whole = explode('.', $value, 2)[0];
                        if (strlen($fraction) > $scale || strlen($whole) > strlen(explode('.', $maximum, 2)[0]) || $this->decimalGreaterThan($value, $maximum)) {
                            $validator->errors()->add("indicators.{$indicatorIndex}.activities.{$activityIndex}.{$field}", 'The value exceeds the permitted precision or range.');
                        }
                    }
                }
            }

            if ($activityCount > 500) {
                $validator->errors()->add('indicators', 'A PKP draft may contain at most 500 activities.');
            }
        });
    }

    private function decimalGreaterThan(string $value, string $maximum): bool
    {
        [$valueWhole, $valueFraction] = array_pad(explode('.', $value, 2), 2, '');
        [$maxWhole, $maxFraction] = array_pad(explode('.', $maximum, 2), 2, '');
        $valueFraction = str_pad($valueFraction, max(strlen($valueFraction), strlen($maxFraction)), '0');
        $maxFraction = str_pad($maxFraction, strlen($valueFraction), '0');

        return strlen($valueWhole) > strlen($maxWhole)
            || (strlen($valueWhole) === strlen($maxWhole) && strcmp($valueWhole, $maxWhole) > 0)
            || ($valueWhole === $maxWhole && strcmp($valueFraction, $maxFraction) > 0);
    }
}
