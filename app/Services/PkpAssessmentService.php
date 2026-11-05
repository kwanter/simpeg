<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Support\PkpContext;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpAssessmentService
{
    public function __construct(
        private readonly PkpAuthorization $authorization,
        private readonly PkpIdentityService $identities,
    ) {}

    public function hasCurrentContentAssignments(User $actor): bool
    {
        $this->authorization->authorize($actor, 'viewAssignedAssessments', new PkpContext);
        $assessorUuid = $actor->pegawai()->value('uuid');
        if ($assessorUuid === null) {
            return false;
        }

        return DB::table('pkp_revisions as revision')
            ->join('pkp', 'pkp.pending_revision_uuid', '=', 'revision.uuid')
            ->join('pkp_submissions as submission', 'submission.uuid', '=', 'revision.current_submission_uuid')
            ->where('revision.assessor_pegawai_uuid', $assessorUuid)
            ->where('revision.state', 'submitted')
            ->where('submission.state', 'submitted')
            ->exists();
    }

    public function hasCurrentDocumentAssignments(User $actor): bool
    {
        $this->authorization->authorize($actor, 'viewAssignedAssessments', new PkpContext);
        $inspectorUuid = $actor->pegawai()->value('uuid');
        if ($inspectorUuid === null) {
            return false;
        }

        return DB::table('pkp_revisions as revision')
            ->join('pkp', 'pkp.official_revision_uuid', '=', 'revision.uuid')
            ->join('pkp_documents as document', 'document.uuid', '=', 'revision.pending_final_document_uuid')
            ->where('revision.inspector_pegawai_uuid', $inspectorUuid)
            ->where('revision.state', 'approved')
            ->where('document.kind', 'signed_final')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('pkp_document_reviews as review')
                ->whereColumn('review.document_uuid', 'document.uuid'))
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queue(User $actor, string $kind): array
    {
        return $this->queuePage($actor, $kind)->items();
    }

    public function queuePage(User $actor, string $kind, int $page = 1): LengthAwarePaginator
    {
        $this->authorization->authorize($actor, 'viewAssignedAssessments', new PkpContext);
        if (! in_array($kind, ['content', 'document'], true)) {
            throw ValidationException::withMessages(['kind' => 'Choose a content or document queue.']);
        }
        if ($page < 1) {
            throw ValidationException::withMessages(['page' => 'The queue page must be at least one.']);
        }

        if ($kind === 'document') {
            $inspector = $actor->pegawai()->firstOrFail();

            $items = DB::table('pkp_revisions as revision')
                ->join('pkp', 'pkp.official_revision_uuid', '=', 'revision.uuid')
                ->join('pkp_documents as document', 'document.uuid', '=', 'revision.pending_final_document_uuid')
                ->join('pkp_submissions as submission', 'submission.uuid', '=', 'revision.approved_submission_uuid')
                ->join('pegawai as owner', 'owner.uuid', '=', 'pkp.pegawai_uuid')
                ->where('revision.inspector_pegawai_uuid', $inspector->uuid)
                ->where('revision.state', 'approved')
                ->where('document.kind', 'signed_final')
                ->where('submission.state', 'approved')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('pkp_document_reviews as review')
                    ->whereColumn('review.document_uuid', 'document.uuid'))
                ->orderByDesc('pkp.year')
                ->orderByDesc('pkp.month')
                ->orderByDesc('pkp.uuid')
                ->select([
                    'pkp.uuid as pkp_uuid',
                    'pkp.pegawai_uuid',
                    'pkp.year',
                    'pkp.month',
                    'pkp.version',
                    'revision.uuid as revision_uuid',
                    'revision.number as revision_number',
                    'submission.uuid as submission_uuid',
                    'document.uuid as document_uuid',
                    'document.original_name',
                    'document.uploaded_at',
                    'owner.nama as employee_name',
                ])->paginate(25, ['*'], 'page', $page);
            $items->getCollection()->transform(fn (object $item): array => [
                'pkp_uuid' => $item->pkp_uuid,
                'pegawai_uuid' => $item->pegawai_uuid,
                'employee_name' => $item->employee_name,
                'year' => (int) $item->year,
                'month' => (int) $item->month,
                'version' => (int) $item->version,
                'revision_uuid' => $item->revision_uuid,
                'revision_number' => (int) $item->revision_number,
                'submission_uuid' => $item->submission_uuid,
                'document_uuid' => $item->document_uuid,
                'original_name' => $item->original_name,
                'uploaded_at' => $item->uploaded_at,
            ]);

            return $items;
        }

        $assessor = $actor->pegawai()->firstOrFail();

        $items = DB::table('pkp_revisions as revision')
            ->join('pkp', 'pkp.pending_revision_uuid', '=', 'revision.uuid')
            ->join('pkp_submissions as submission', 'submission.uuid', '=', 'revision.current_submission_uuid')
            ->join('pegawai as owner', 'owner.uuid', '=', 'pkp.pegawai_uuid')
            ->where('revision.assessor_pegawai_uuid', $assessor->uuid)
            ->where('revision.state', 'submitted')
            ->where('submission.state', 'submitted')
            ->orderByDesc('pkp.year')
            ->orderByDesc('pkp.month')
            ->orderByDesc('pkp.uuid')
            ->select([
                'pkp.uuid as pkp_uuid',
                'pkp.pegawai_uuid',
                'pkp.year',
                'pkp.month',
                'pkp.version',
                'revision.uuid as revision_uuid',
                'revision.number as revision_number',
                'submission.uuid as submission_uuid',
                'submission.submitted_at',
                'owner.nama as employee_name',
            ])->paginate(25, ['*'], 'page', $page);
        $items->getCollection()->transform(fn (object $item): array => [
            'pkp_uuid' => $item->pkp_uuid,
            'pegawai_uuid' => $item->pegawai_uuid,
            'employee_name' => $item->employee_name,
            'year' => (int) $item->year,
            'month' => (int) $item->month,
            'version' => (int) $item->version,
            'revision_uuid' => $item->revision_uuid,
            'revision_number' => (int) $item->revision_number,
            'submission_uuid' => $item->submission_uuid,
            'submitted_at' => $item->submitted_at,
        ]);

        return $items;
    }

    public function decide(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        string $submissionUuid,
        int $expectedVersion,
        string $decision,
        ?string $reason,
        DateTimeImmutable $serverNow,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $submissionUuid, $expectedVersion, $decision, $reason, $serverNow): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();
            $submission = DB::table('pkp_submissions')
                ->where('uuid', $submissionUuid)
                ->where('revision_uuid', $revisionUuid)
                ->lockForUpdate()
                ->first();

            if ($revision === null || $submission === null) {
                abort(404, 'submission_not_found');
            }

            $this->authorization->authorize($actor, 'contentDecision', new PkpContext($pkp, $revision, submission: $submission));

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }

            if ($revision->current_submission_uuid !== $submissionUuid || $submission->state !== 'submitted') {
                abort(409, 'submission_already_decided');
            }

            if (! in_array($decision, ['approve', 'return'], true)) {
                throw ValidationException::withMessages(['decision' => 'Choose approve or return.']);
            }
            if ($decision === 'return' && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'A reason is required when returning a PKP.']);
            }

            $now = CarbonImmutable::instance($serverNow)->utc();
            if ($decision === 'approve') {
                $approvalAllowedAt = CarbonImmutable::create($pkp->year, $pkp->month, 1, 0, 0, 0, 'Asia/Makassar')
                    ->addMonthNoOverflow()
                    ->startOfMonth();
                if ($now->setTimezone('Asia/Makassar')->lessThan($approvalAllowedAt)) {
                    abort(409, 'period_not_finished');
                }

                $owner = Pegawai::query()->findOrFail($pkp->pegawai_uuid);
                try {
                    $currentIdentity = $this->identities->resolve($owner, $pkp->year, $pkp->month);
                } catch (ValidationException) {
                    abort(409, 'identity_changed');
                }

                $identityHash = hash('sha256', json_encode($currentIdentity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                if (! hash_equals($submission->identity_sha256, $identityHash)) {
                    abort(409, 'identity_changed');
                }
            }

            $approvedFinalDocumentUuid = null;
            if ($decision === 'approve' && $revision->source === 'external' && $revision->source_document_uuid !== null) {
                $sourceDocument = DB::table('pkp_documents')
                    ->where('uuid', $revision->source_document_uuid)
                    ->where('revision_uuid', $revisionUuid)
                    ->where('kind', 'source')
                    ->first();
                if ($sourceDocument !== null && (bool) $sourceDocument->declared_signed) {
                    $approvedFinalDocumentUuid = $sourceDocument->uuid;
                }
            }

            $assessor = $actor->pegawai()->firstOrFail();
            $nextVersion = $expectedVersion + 1;
            DB::table('pkp_decisions')->insert([
                'uuid' => (string) Str::uuid(),
                'submission_uuid' => $submissionUuid,
                'actor_user_uuid' => $actor->uuid,
                'decision' => $decision,
                'reason' => $decision === 'return' ? trim((string) $reason) : null,
                'decided_at' => $now,
                'created_at' => $now,
            ]);

            DB::table('pkp_submissions')->where('uuid', $submissionUuid)->update([
                'state' => $decision === 'approve' ? 'approved' : 'returned',
                'updated_at' => $now,
            ]);

            $revisionChanges = [
                'state' => $decision === 'approve' ? 'approved' : 'returned',
                'updated_at' => $now,
            ];
            if ($decision === 'approve') {
                $revisionChanges['approved_submission_uuid'] = $submissionUuid;
                $revisionChanges['approved_assessor_snapshot'] = json_encode([
                    'pegawai_uuid' => $assessor->uuid,
                    'name' => $assessor->nama,
                    'nip' => $assessor->nip,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $revisionChanges['inspector_pegawai_uuid'] = $assessor->uuid;
                $revisionChanges['approved_at'] = $now;
                if ($approvedFinalDocumentUuid !== null) {
                    $revisionChanges['final_document_uuid'] = $approvedFinalDocumentUuid;
                }
            }
            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update($revisionChanges);

            if ($approvedFinalDocumentUuid !== null) {
                DB::table('pkp_document_reviews')->insert([
                    'uuid' => (string) Str::uuid(),
                    'document_uuid' => $approvedFinalDocumentUuid,
                    'actor_user_uuid' => $actor->uuid,
                    'decision' => 'accept',
                    'reason' => null,
                    'basis' => 'content_approval_reuse',
                    'approved_submission_uuid' => $submissionUuid,
                    'replacement_base_document_uuid' => null,
                    'matches_approved_content' => null,
                    'decided_at' => $now,
                    'created_at' => $now,
                ]);
            }

            $officialRevisionUuid = $pkp->official_revision_uuid;
            $pendingRevisionUuid = $revisionUuid;
            if ($decision === 'approve') {
                $officialRevisionUuid = $revisionUuid;
                $pendingRevisionUuid = null;
            }

            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'version' => $nextVersion,
                'official_revision_uuid' => $officialRevisionUuid,
                'pending_revision_uuid' => $pendingRevisionUuid,
                'updated_at' => $now,
            ]);
            DB::table('pkp_events')->insert([
                'uuid' => (string) Str::uuid(),
                'pkp_uuid' => $pkpUuid,
                'revision_uuid' => $revisionUuid,
                'actor_user_uuid' => $actor->uuid,
                'event_type' => $decision === 'approve' ? 'content_approved' : 'content_returned',
                'before_json' => json_encode(['state' => 'submitted', 'version' => $expectedVersion], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['state' => $decision === 'approve' ? 'approved' : 'returned', 'version' => $nextVersion, 'submission_uuid' => $submissionUuid, 'reason' => $reason], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult(
                $pkpUuid,
                $nextVersion,
                $officialRevisionUuid,
                $pendingRevisionUuid,
                $submissionUuid,
                null,
                $decision === 'approve' && $approvedFinalDocumentUuid !== null
                    ? 'complete'
                    : 'incomplete',
                $pkp->lateness
            );
        });
    }
}
