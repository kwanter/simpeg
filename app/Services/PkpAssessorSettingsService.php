<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\User;
use App\Support\ConfigurationResult;
use App\Support\PkpContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpAssessorSettingsService
{
    public function __construct(private readonly PkpAuthorization $authorization) {}

    /**
     * @param  list<string>  $allowedUuids
     */
    public function configure(
        User $actor,
        string $pegawaiUuid,
        int $expectedVersion,
        ?string $defaultUuid,
        array $allowedUuids,
    ): ConfigurationResult {
        return DB::transaction(function () use ($actor, $pegawaiUuid, $expectedVersion, $defaultUuid, $allowedUuids): ConfigurationResult {
            $owner = Pegawai::query()->lockForUpdate()->findOrFail($pegawaiUuid);
            $this->authorization->authorize($actor, 'manageAssessorSettings', new PkpContext(targetPegawai: $owner));
            $this->validateAssessorSet($owner, $defaultUuid, $allowedUuids);

            $settings = DB::table('pkp_assessor_settings')
                ->where('pegawai_uuid', $owner->uuid)
                ->lockForUpdate()
                ->first();

            $currentVersion = (int) ($settings->version ?? 0);
            if ($expectedVersion !== $currentVersion) {
                abort(409, 'stale_configuration');
            }

            $now = now();
            $configurationUuid = $settings?->uuid ?? (string) Str::uuid();
            $before = $settings === null ? null : $this->currentValues($settings->uuid, $settings->default_assessor_uuid);
            $version = $currentVersion + 1;
            $values = [
                'default_assessor_uuid' => $defaultUuid,
                'allowed_assessor_uuids' => array_values($allowedUuids),
            ];

            if ($settings === null) {
                DB::table('pkp_assessor_settings')->insert([
                    'uuid' => $configurationUuid,
                    'pegawai_uuid' => $owner->uuid,
                    'default_assessor_uuid' => $defaultUuid,
                    'version' => $version,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('pkp_assessor_settings')->where('uuid', $configurationUuid)->update([
                    'default_assessor_uuid' => $defaultUuid,
                    'version' => $version,
                    'updated_at' => $now,
                ]);
                DB::table('pkp_allowed_assessors')->where('settings_uuid', $configurationUuid)->delete();
            }

            DB::table('pkp_allowed_assessors')->insert(array_map(
                fn (string $assessorUuid): array => [
                    'settings_uuid' => $configurationUuid,
                    'assessor_pegawai_uuid' => $assessorUuid,
                ],
                $allowedUuids
            ));

            DB::table('pkp_configuration_events')->insert([
                'uuid' => (string) Str::uuid(),
                'kind' => 'assessor_settings',
                'configuration_uuid' => $configurationUuid,
                'actor_user_uuid' => $actor->uuid,
                'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($values, JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return new ConfigurationResult($configurationUuid, $version, $defaultUuid, array_values($allowedUuids));
        });
    }

    /**
     * @param  list<string>  $allowedUuids
     */
    private function validateAssessorSet(Pegawai $owner, ?string $defaultUuid, array $allowedUuids): void
    {
        if ($allowedUuids === [] || count($allowedUuids) !== count(array_unique($allowedUuids))) {
            throw ValidationException::withMessages([
                'allowed_assessor_uuids' => 'Select one or more distinct eligible assessors.',
            ]);
        }

        if (in_array($owner->uuid, $allowedUuids, true)) {
            throw ValidationException::withMessages([
                'allowed_assessor_uuids' => 'An employee cannot assess their own PKP.',
            ]);
        }

        if ($defaultUuid !== null && ! in_array($defaultUuid, $allowedUuids, true)) {
            throw ValidationException::withMessages([
                'default_assessor_uuid' => 'The default assessor must be in the allowed set.',
            ]);
        }

        $eligibleCount = Pegawai::query()
            ->whereIn('pegawai.uuid', $allowedUuids)
            ->whereHas('user', fn ($query) => $query
                ->where('status', '1')
                ->whereNotNull('email_verified_at'))
            ->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))
            ->count();

        if ($eligibleCount !== count($allowedUuids)) {
            throw ValidationException::withMessages([
                'allowed_assessor_uuids' => 'Every assessor must be an active employee with a verified account.',
            ]);
        }
    }

    /**
     * @return array{default_assessor_uuid: ?string, allowed_assessor_uuids: list<string>}
     */
    private function currentValues(string $settingsUuid, ?string $defaultUuid): array
    {
        return [
            'default_assessor_uuid' => $defaultUuid,
            'allowed_assessor_uuids' => DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settingsUuid)
                ->orderBy('assessor_pegawai_uuid')
                ->pluck('assessor_pegawai_uuid')
                ->all(),
        ];
    }
}
