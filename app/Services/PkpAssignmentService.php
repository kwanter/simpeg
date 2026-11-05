<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Support\PkpContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpAssignmentService
{
    public function __construct(private readonly PkpAuthorization $authorization) {}

    public function reassignInspector(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        string $newInspectorUuid,
        string $reason,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $newInspectorUuid, $reason): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::query()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();
            if ($revision === null) {
                abort(404, 'revision_not_found');
            }

            $target = Pegawai::query()->lockForUpdate()->findOrFail($newInspectorUuid);
            $this->authorization->authorize(
                $actor,
                'reassignInspector',
                new PkpContext($pkp, $revision, targetPegawai: $target)
            );

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }
            if ($pkp->official_revision_uuid !== $revisionUuid || $revision->state !== 'approved') {
                abort(409, 'revision_not_official');
            }
            if ($revision->inspector_pegawai_uuid === $target->uuid) {
                abort(409, 'inspector_unchanged');
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A reassignment reason is required.']);
            }
            if ($target->trashed()
                || $target->user_uuid === null
                || ! User::query()->where('uuid', $target->user_uuid)
                    ->where('status', '1')
                    ->whereNotNull('email_verified_at')
                    ->exists()) {
                throw ValidationException::withMessages(['inspector_pegawai_uuid' => 'Choose an active employee with a verified account.']);
            }

            $settingsUuid = DB::table('pkp_assessor_settings')
                ->where('pegawai_uuid', $owner->uuid)
                ->lockForUpdate()
                ->value('uuid');
            $isAllowed = $settingsUuid !== null && DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settingsUuid)
                ->where('assessor_pegawai_uuid', $target->uuid)
                ->exists();
            if (! $isAllowed) {
                throw ValidationException::withMessages(['inspector_pegawai_uuid' => 'Choose an inspector in the employee allowed list.']);
            }

            $now = now('UTC');
            $nextVersion = $expectedVersion + 1;
            $previousInspectorUuid = $revision->inspector_pegawai_uuid;
            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'inspector_pegawai_uuid' => $target->uuid,
                'updated_at' => $now,
            ]);
            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'version' => $nextVersion,
                'updated_at' => $now,
            ]);
            DB::table('pkp_events')->insert([
                'uuid' => (string) Str::uuid(),
                'pkp_uuid' => $pkpUuid,
                'revision_uuid' => $revisionUuid,
                'actor_user_uuid' => $actor->uuid,
                'event_type' => 'inspector_reassigned',
                'before_json' => json_encode([
                    'inspector_pegawai_uuid' => $previousInspectorUuid,
                    'version' => $expectedVersion,
                ], JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'inspector_pegawai_uuid' => $target->uuid,
                    'reason' => trim($reason),
                    'version' => $nextVersion,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult(
                $pkpUuid,
                $nextVersion,
                $pkp->official_revision_uuid,
                $pkp->pending_revision_uuid,
                $revision->approved_submission_uuid,
                $revision->pending_final_document_uuid,
                $revision->final_document_uuid === null ? 'awaiting_signed_upload' : 'complete',
                $pkp->lateness
            );
        });
    }

    public function reassignContent(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        string $newAssessorUuid,
        string $reason,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $newAssessorUuid, $reason): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::query()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();
            if ($revision === null) {
                abort(404, 'revision_not_found');
            }

            $target = Pegawai::query()->lockForUpdate()->findOrFail($newAssessorUuid);
            $this->authorization->authorize(
                $actor,
                'reassignContent',
                new PkpContext($pkp, $revision, targetPegawai: $target)
            );

            if ($pkp->official_revision_uuid === $revisionUuid || $revision->state === 'approved') {
                abort(409, 'revision_approved');
            }
            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }
            if ($pkp->pending_revision_uuid !== $revisionUuid || ! in_array($revision->state, ['submitted', 'returned'], true)) {
                abort(409, 'revision_not_reassignable');
            }
            if ($revision->assessor_pegawai_uuid === $target->uuid) {
                abort(409, 'assessor_unchanged');
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A reassignment reason is required.']);
            }
            if ($target->trashed()
                || $target->user_uuid === null
                || ! User::query()->where('uuid', $target->user_uuid)
                    ->where('status', '1')
                    ->whereNotNull('email_verified_at')
                    ->exists()) {
                throw ValidationException::withMessages(['assessor_pegawai_uuid' => 'Choose an active employee with a verified account.']);
            }

            $settingsUuid = DB::table('pkp_assessor_settings')
                ->where('pegawai_uuid', $owner->uuid)
                ->lockForUpdate()
                ->value('uuid');
            $isAllowed = $settingsUuid !== null && DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settingsUuid)
                ->where('assessor_pegawai_uuid', $target->uuid)
                ->exists();
            if (! $isAllowed) {
                throw ValidationException::withMessages(['assessor_pegawai_uuid' => 'Choose an assessor in the employee allowed list.']);
            }

            $currentSubmission = $revision->current_submission_uuid === null
                ? null
                : DB::table('pkp_submissions')
                    ->where('uuid', $revision->current_submission_uuid)
                    ->where('revision_uuid', $revisionUuid)
                    ->lockForUpdate()
                    ->first();
            if ($revision->state === 'submitted'
                && ($currentSubmission === null || $currentSubmission->state !== 'submitted')) {
                abort(409, 'submission_not_current');
            }

            $now = now('UTC');
            $nextVersion = $expectedVersion + 1;
            $previousAssessorUuid = $revision->assessor_pegawai_uuid;
            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'assessor_pegawai_uuid' => $target->uuid,
                'updated_at' => $now,
            ]);
            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'version' => $nextVersion,
                'updated_at' => $now,
            ]);
            DB::table('pkp_events')->insert([
                'uuid' => (string) Str::uuid(),
                'pkp_uuid' => $pkpUuid,
                'revision_uuid' => $revisionUuid,
                'actor_user_uuid' => $actor->uuid,
                'event_type' => 'assessor_reassigned',
                'before_json' => json_encode([
                    'assessor_pegawai_uuid' => $previousAssessorUuid,
                    'version' => $expectedVersion,
                ], JSON_THROW_ON_ERROR),
                'after_json' => json_encode([
                    'assessor_pegawai_uuid' => $target->uuid,
                    'reason' => trim($reason),
                    'version' => $nextVersion,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult(
                $pkpUuid,
                $nextVersion,
                $pkp->official_revision_uuid,
                $pkp->pending_revision_uuid,
                $currentSubmission?->uuid,
                null,
                'incomplete',
                $pkp->lateness
            );
        });
    }
}
