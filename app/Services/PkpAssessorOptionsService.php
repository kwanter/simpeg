<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\User;
use App\Support\PkpContext;
use Illuminate\Support\Facades\DB;

class PkpAssessorOptionsService
{
    public function __construct(private readonly PkpAuthorization $authorization) {}

    public function forOwner(User $actor): AssessorOptionsResult
    {
        $owner = $actor->pegawai()->first();
        $this->authorization->authorize($actor, 'viewOwnAssessorOptions', new PkpContext(targetPegawai: $owner));

        $settings = DB::table('pkp_assessor_settings')->where('pegawai_uuid', $owner->uuid)->first();
        $allowedUuids = $settings === null
            ? []
            : DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settings->uuid)
                ->pluck('assessor_pegawai_uuid')
                ->all();

        $eligibleOptions = $allowedUuids === []
            ? []
            : Pegawai::query()
                ->whereIn('pegawai.uuid', $allowedUuids)
                ->where('pegawai.uuid', '!=', $owner->uuid)
                ->whereHas('user', fn ($query) => $query->where('status', '1')->whereNotNull('email_verified_at'))
                ->orderBy('nama')
                ->get(['uuid', 'nama', 'nip'])
                ->map(fn (Pegawai $pegawai): array => [
                    'pegawai_uuid' => $pegawai->uuid,
                    'name' => $pegawai->nama,
                    'nip' => $pegawai->nip,
                ])
                ->values()
                ->all();

        $eligibleDefaultUuid = collect($eligibleOptions)
            ->contains(fn (array $option): bool => $option['pegawai_uuid'] === ($settings->default_assessor_uuid ?? null))
            ? $settings->default_assessor_uuid
            : null;

        return new AssessorOptionsResult(
            (int) ($settings->version ?? 0),
            $settings->default_assessor_uuid ?? null,
            $eligibleDefaultUuid,
            $eligibleOptions
        );
    }
}

class AssessorOptionsResult
{
    /**
     * @param  list<array{pegawai_uuid: string, name: ?string, nip: string}>  $eligibleOptions
     */
    public function __construct(
        public readonly int $settingsVersion,
        public readonly ?string $configuredDefaultUuid,
        public readonly ?string $eligibleDefaultUuid,
        public readonly array $eligibleOptions,
    ) {}
}
