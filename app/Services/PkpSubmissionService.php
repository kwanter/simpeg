<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Support\ActivityInput;
use App\Support\CalculationResult;
use App\Support\IndicatorInput;
use App\Support\PkpContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PkpSubmissionService
{
    public function __construct(
        private readonly PkpAuthorization $authorization,
        private readonly PkpIdentityService $identities,
        private readonly PkpCalculator $calculator,
    ) {}

    public function list(User $actor, string $scope, int $year): LengthAwarePaginator
    {
        if ($scope === 'all') {
            $this->authorization->authorize($actor, 'viewAll', new PkpContext);
        } else {
            $this->authorization->authorize($actor, 'viewOwnList', new PkpContext(targetPegawai: $actor->pegawai));
        }

        $query = DB::table('pkp')
            ->join('pegawai', 'pegawai.uuid', '=', 'pkp.pegawai_uuid')
            ->leftJoin('pkp_revisions as official_revision', 'official_revision.uuid', '=', 'pkp.official_revision_uuid')
            ->leftJoin('pkp_revisions as pending_revision', 'pending_revision.uuid', '=', 'pkp.pending_revision_uuid')
            ->where('pkp.year', $year)
            ->when($scope === 'own', fn ($query) => $query->where('pkp.pegawai_uuid', $actor->pegawai->uuid))
            ->select([
                'pkp.uuid as pkp_uuid',
                'pkp.pegawai_uuid',
                'pegawai.nama as pegawai_name',
                'pegawai.nip',
                'pkp.year',
                'pkp.month',
                'pkp.official_revision_uuid',
                'pkp.pending_revision_uuid',
                'pkp.lateness',
                'official_revision.state as official_revision_state',
                'official_revision.final_document_uuid',
            ])
            ->orderByDesc('pkp.year')
            ->orderByDesc('pkp.month')
            ->orderByDesc('pkp.uuid');

        $page = $query->paginate(25);
        $page->getCollection()->transform(function (object $row): array {
            $contentApproved = $row->official_revision_state === 'approved';
            $documentComplete = $row->final_document_uuid !== null;

            return [
                'pkp_uuid' => $row->pkp_uuid,
                'pegawai_uuid' => $row->pegawai_uuid,
                'pegawai_name' => $row->pegawai_name,
                'nip' => $row->nip,
                'year' => (int) $row->year,
                'month' => (int) $row->month,
                'official_revision_uuid' => $row->official_revision_uuid,
                'pending_revision_uuid' => $row->pending_revision_uuid,
                'content_approved' => $contentApproved,
                'document_complete' => $documentComplete,
                'completeness' => $documentComplete ? 'complete' : ($contentApproved ? 'awaiting_signed_upload' : 'incomplete'),
                'lateness' => $row->lateness,
            ];
        });

        return $page;
    }

    public function create(User $actor, int $year, int $month, string $source, ?string $assessorUuid = null): PkpMutationResult
    {
        return DB::transaction(function () use ($actor, $year, $month, $source, $assessorUuid): PkpMutationResult {
            $owner = $actor->pegawai()->lockForUpdate()->first();
            $this->authorization->authorize($actor, 'create', new PkpContext(targetPegawai: $owner));

            $duplicate = DB::table('pkp')
                ->where('pegawai_uuid', $owner->uuid)
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                abort(409, 'duplicate_period');
            }

            $settings = DB::table('pkp_assessor_settings')
                ->where('pegawai_uuid', $owner->uuid)
                ->lockForUpdate()
                ->first();
            $selectedAssessorUuid = $this->resolveAssessor($owner, $settings, $assessorUuid);
            $now = now();
            $pkpUuid = (string) Str::uuid();
            $revisionUuid = (string) Str::uuid();

            Pkp::query()->create([
                'uuid' => $pkpUuid,
                'pegawai_uuid' => $owner->uuid,
                'year' => $year,
                'month' => $month,
                'version' => 1,
            ]);

            DB::table('pkp_revisions')->insert([
                'uuid' => $revisionUuid,
                'pkp_uuid' => $pkpUuid,
                'number' => 1,
                'source' => $source,
                'state' => 'draft',
                'assessor_pegawai_uuid' => $selectedAssessorUuid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'pending_revision_uuid' => $revisionUuid,
                'updated_at' => $now,
            ]);

            if ($settings === null) {
                DB::table('pkp_assessor_settings')->insert([
                    'uuid' => (string) Str::uuid(),
                    'pegawai_uuid' => $owner->uuid,
                    'default_assessor_uuid' => null,
                    'version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('pkp_deadlines')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'year' => $year,
                'month' => $month,
                'deadline_at' => null,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return new PkpMutationResult($pkpUuid, 1, null, $revisionUuid);
        });
    }

    public function revise(
        User $actor,
        string $pkpUuid,
        int $expectedVersion,
        string $baseRevisionUuid,
        string $source,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $expectedVersion, $baseRevisionUuid, $source): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::withTrashed()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $base = DB::table('pkp_revisions')
                ->where('uuid', $baseRevisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();

            $this->authorization->authorize($actor, 'revise', new PkpContext($pkp, $base, targetPegawai: $owner));

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }
            if ($pkp->pending_revision_uuid !== null) {
                abort(409, 'pending_revision_exists');
            }
            if ($base === null || $pkp->official_revision_uuid !== $baseRevisionUuid || $base->state !== 'approved') {
                abort(409, 'base_revision_changed');
            }
            if (! in_array($source, ['application', 'external'], true)) {
                throw ValidationException::withMessages(['source' => 'Choose application or external.']);
            }

            $approvedSubmission = DB::table('pkp_submissions')
                ->where('uuid', $base->approved_submission_uuid)
                ->where('revision_uuid', $base->uuid)
                ->where('state', 'approved')
                ->first();
            if ($approvedSubmission === null) {
                abort(409, 'base_revision_changed');
            }

            $assessorSettings = DB::table('pkp_assessor_settings')
                ->where('pegawai_uuid', $owner->uuid)
                ->lockForUpdate()
                ->first();
            $assessorUuid = $this->resolveAssessor($owner, $assessorSettings, null);
            $approvedInput = json_decode($approvedSubmission->input_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $copyApplicationInputs = $source === 'application' && $base->source === 'application';
            $revisionUuid = (string) Str::uuid();
            $now = now('UTC');
            $number = (int) DB::table('pkp_revisions')->where('pkp_uuid', $pkpUuid)->max('number') + 1;

            DB::table('pkp_revisions')->insert([
                'uuid' => $revisionUuid,
                'pkp_uuid' => $pkpUuid,
                'number' => $number,
                'source' => $source,
                'state' => 'draft',
                'assessor_pegawai_uuid' => $assessorUuid,
                'notes' => $copyApplicationInputs ? ($approvedInput['notes'] ?? null) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($copyApplicationInputs) {
                $this->replaceActivityTree($revisionUuid, $approvedInput['indicators'] ?? [], $now);
            }

            $nextVersion = $expectedVersion + 1;
            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'pending_revision_uuid' => $revisionUuid,
                'version' => $nextVersion,
                'updated_at' => $now,
            ]);
            DB::table('pkp_events')->insert([
                'uuid' => (string) Str::uuid(),
                'pkp_uuid' => $pkpUuid,
                'revision_uuid' => $revisionUuid,
                'actor_user_uuid' => $actor->uuid,
                'event_type' => 'correction_created',
                'before_json' => json_encode(['official_revision_uuid' => $baseRevisionUuid, 'pending_revision_uuid' => null, 'version' => $expectedVersion], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['official_revision_uuid' => $baseRevisionUuid, 'pending_revision_uuid' => $revisionUuid, 'source' => $source, 'version' => $nextVersion], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult($pkpUuid, $nextVersion, $baseRevisionUuid, $revisionUuid);
        });
    }

    public function refreshIdentity(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        string $reason,
        DateTimeImmutable $serverNow,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $reason, $serverNow): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::withTrashed()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();
            if ($revision === null || $pkp->pending_revision_uuid !== $revisionUuid) {
                abort(404, 'revision_not_found');
            }

            $this->authorization->authorize($actor, 'refreshIdentity', new PkpContext($pkp, $revision, targetPegawai: $owner));
            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }
            if ($revision->state !== 'submitted' || $revision->current_submission_uuid === null) {
                abort(409, 'revision_not_submitted');
            }

            $submission = DB::table('pkp_submissions')
                ->where('uuid', $revision->current_submission_uuid)
                ->where('revision_uuid', $revisionUuid)
                ->lockForUpdate()
                ->first();
            if ($submission === null || $submission->state !== 'submitted') {
                abort(409, 'submission_not_current');
            }

            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'A reason is required to refresh changed identity.']);
            }

            $currentIdentity = $this->identities->resolve($owner, $pkp->year, $pkp->month);
            $currentIdentityHash = hash('sha256', json_encode($currentIdentity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if (hash_equals($submission->identity_sha256, $currentIdentityHash)) {
                abort(409, 'identity_unchanged');
            }

            $now = CarbonImmutable::instance($serverNow)->utc();
            $nextVersion = $expectedVersion + 1;
            DB::table('pkp_submissions')->where('uuid', $submission->uuid)->update([
                'state' => 'withdrawn_identity_changed',
                'updated_at' => $now,
            ]);
            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'state' => 'returned',
                'current_submission_uuid' => null,
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
                'event_type' => 'identity_refreshed',
                'before_json' => json_encode(['submission_uuid' => $submission->uuid, 'identity_sha256' => $submission->identity_sha256, 'state' => 'submitted'], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['identity_sha256' => $currentIdentityHash, 'state' => 'returned', 'reason' => trim($reason)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult($pkpUuid, $nextVersion, $pkp->official_revision_uuid, $revisionUuid, $submission->uuid, null, 'incomplete', $pkp->lateness);
        });
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function save(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        array $draft,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $draft): PkpMutationResult {
            $record = Pkp::query()->findOrFail($pkpUuid);
            DB::table('pkp_deadlines')->where('year', $record->year)->where('month', $record->month)->lockForUpdate()->first();
            $settings = DB::table('pkp_assessor_settings')->where('pegawai_uuid', $record->pegawai_uuid)->lockForUpdate()->first();
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::withTrashed()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();

            if ($revision === null || $pkp->pending_revision_uuid !== $revisionUuid) {
                abort(404, 'revision_not_found');
            }

            $this->authorization->authorize($actor, 'save', new PkpContext($pkp, $revision, targetPegawai: $owner));

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }

            if (! in_array($revision->state, ['draft', 'returned'], true)) {
                abort(409, 'revision_not_editable');
            }

            if ($revision->source === 'external' && array_key_exists('indicators', $draft)) {
                throw ValidationException::withMessages([
                    'indicators' => 'External PKP drafts cannot contain structured activity data.',
                ]);
            }

            $assessorUuid = $revision->assessor_pegawai_uuid;
            if (array_key_exists('assessor_pegawai_uuid', $draft)) {
                if (! is_string($draft['assessor_pegawai_uuid'])) {
                    throw ValidationException::withMessages(['assessor_pegawai_uuid' => 'Choose an eligible allowed assessor.']);
                }
                $assessorUuid = $draft['assessor_pegawai_uuid'];
            }
            if ($assessorUuid !== null) {
                $this->resolveAssessor($owner, $settings, $assessorUuid);
            }
            if (array_key_exists('notes', $draft) && $draft['notes'] !== null
                && (! is_string($draft['notes']) || mb_strlen($draft['notes']) > 5000)) {
                throw ValidationException::withMessages(['notes' => 'Notes must be text of at most 5000 characters.']);
            }

            $now = now();
            if (array_key_exists('indicators', $draft)) {
                $this->replaceActivityTree($revisionUuid, $draft['indicators'], $now);
            }

            $nextVersion = $expectedVersion + 1;
            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'assessor_pegawai_uuid' => $assessorUuid,
                'notes' => array_key_exists('notes', $draft) ? $draft['notes'] : $revision->notes,
                'updated_at' => $now,
            ]);
            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'version' => $nextVersion,
                'updated_at' => $now,
            ]);

            return new PkpMutationResult($pkpUuid, $nextVersion, null, $revisionUuid);
        });
    }

    public function submit(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        DateTimeImmutable $serverNow,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $serverNow): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $owner = Pegawai::withTrashed()->lockForUpdate()->findOrFail($pkp->pegawai_uuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();

            if ($revision === null || $pkp->pending_revision_uuid !== $revisionUuid) {
                abort(404, 'revision_not_found');
            }

            $this->authorization->authorize($actor, 'submit', new PkpContext($pkp, $revision, targetPegawai: $owner));

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }

            if (! in_array($revision->state, ['draft', 'returned'], true)) {
                abort(409, 'revision_not_submittable');
            }

            $localNow = CarbonImmutable::instance($serverNow)->setTimezone('Asia/Makassar');
            if ($pkp->year > $localNow->year || ($pkp->year === $localNow->year && $pkp->month > $localNow->month)) {
                throw ValidationException::withMessages([
                    'period' => 'period_not_submittable',
                ]);
            }

            $assessor = $this->resolveAssignedAssessor($owner, $revision->assessor_pegawai_uuid);
            $identity = $this->identities->resolve($owner, $pkp->year, $pkp->month);
            $indicators = $this->storedActivityTree($revisionUuid);
            $calculation = null;
            $sourceDocument = null;

            if ($revision->source === 'application') {
                $inputSnapshot = [
                    'source' => $revision->source,
                    'notes' => $revision->notes,
                    'indicators' => $indicators,
                ];
                $calculation = $this->calculateCompleteApplication($indicators);
            } elseif ($revision->source === 'external') {
                $sourceDocument = $revision->source_document_uuid === null
                    ? null
                    : DB::table('pkp_documents')
                        ->where('uuid', $revision->source_document_uuid)
                        ->where('revision_uuid', $revisionUuid)
                        ->where('kind', 'source')
                        ->first();
                if ($sourceDocument === null) {
                    throw ValidationException::withMessages([
                        'source_document_uuid' => 'An external PKP requires its source PDF before submission.',
                    ]);
                }
                if (! Storage::disk('local')->exists($sourceDocument->storage_path)) {
                    abort(503, 'document_storage_failed');
                }

                $inputSnapshot = [
                    'source' => 'external',
                    'notes' => $revision->notes,
                    'source_document_uuid' => $sourceDocument->uuid,
                    'source_sha256' => $sourceDocument->sha256,
                    'declared_signed' => (bool) $sourceDocument->declared_signed,
                    'indicators' => [],
                ];
            } else {
                throw ValidationException::withMessages(['source' => 'Choose a supported PKP source.']);
            }

            $assessorSnapshot = [
                'pegawai_uuid' => $assessor->uuid,
                'name' => $assessor->nama,
                'nip' => $assessor->nip,
            ];
            $payloadHash = hash('sha256', json_encode($inputSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $identityHash = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $now = CarbonImmutable::instance($serverNow)->utc();
            $deadline = $this->deadlineFor($pkp->year, $pkp->month);
            $firstSubmission = $pkp->first_submitted_at === null;
            $firstSubmittedAt = $firstSubmission ? $now->format('Y-m-d H:i:s.u') : $pkp->getRawOriginal('first_submitted_at');
            $deadlineSnapshotAt = $firstSubmission ? ($deadline->deadline_at ?? null) : $pkp->getRawOriginal('deadline_snapshot_at');
            $lateness = $firstSubmission
                ? $this->classifyLateness($now, $deadline->deadline_at ?? null)
                : $pkp->lateness;
            $attemptNumber = (int) DB::table('pkp_submissions')
                ->where('revision_uuid', $revisionUuid)
                ->max('number') + 1;
            $submissionUuid = (string) Str::uuid();
            $nextVersion = $expectedVersion + 1;
            $scoreSnapshot = $calculation?->toArray();

            DB::table('pkp_submissions')->insert([
                'uuid' => $submissionUuid,
                'revision_uuid' => $revisionUuid,
                'number' => $attemptNumber,
                'state' => 'submitted',
                'input_snapshot' => json_encode($inputSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'identity_snapshot' => json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'assessor_snapshot' => json_encode($assessorSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'source_document_uuid' => $sourceDocument?->uuid,
                'source_sha256' => $sourceDocument?->sha256,
                'calculation_version' => $calculation?->version,
                'score_snapshot' => $scoreSnapshot === null ? null : json_encode($scoreSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'payload_sha256' => $payloadHash,
                'identity_sha256' => $identityHash,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'state' => 'submitted',
                'current_submission_uuid' => $submissionUuid,
                'updated_at' => $now,
            ]);
            DB::table('pkp')->where('uuid', $pkpUuid)->update([
                'version' => $nextVersion,
                'first_submitted_at' => $firstSubmittedAt,
                'deadline_snapshot_at' => $deadlineSnapshotAt,
                'lateness' => $lateness,
                'updated_at' => $now,
            ]);
            DB::table('pkp_events')->insert([
                'uuid' => (string) Str::uuid(),
                'pkp_uuid' => $pkpUuid,
                'revision_uuid' => $revisionUuid,
                'actor_user_uuid' => $actor->uuid,
                'event_type' => 'submitted',
                'before_json' => json_encode(['state' => $revision->state, 'version' => $expectedVersion], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['state' => 'submitted', 'version' => $nextVersion, 'submission_uuid' => $submissionUuid, 'lateness' => $lateness], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
            ]);

            return new PkpMutationResult(
                $pkpUuid,
                $nextVersion,
                null,
                $revisionUuid,
                $submissionUuid,
                null,
                'awaiting_content_approval',
                $lateness
            );
        });
    }

    /**
     * @return array{uuid: string, nama: ?string, nip: ?string}
     */
    private function resolveAssignedAssessor(Pegawai $owner, ?string $assessorUuid): Pegawai
    {
        if ($assessorUuid === null || $assessorUuid === $owner->uuid) {
            throw ValidationException::withMessages([
                'assessor_pegawai_uuid' => 'Choose an eligible allowed Pejabat Penilai before submission.',
            ]);
        }

        $settings = DB::table('pkp_assessor_settings')
            ->where('pegawai_uuid', $owner->uuid)
            ->lockForUpdate()
            ->first();
        $allowed = $settings !== null && DB::table('pkp_allowed_assessors')
            ->where('settings_uuid', $settings->uuid)
            ->where('assessor_pegawai_uuid', $assessorUuid)
            ->exists();
        $assessor = Pegawai::query()->with('user')->find($assessorUuid);

        if (! $allowed || $assessor === null || $assessor->user === null
            || $assessor->user->status !== '1'
            || $assessor->user->email_verified_at === null
            || $assessor->user->trashed()
        ) {
            throw ValidationException::withMessages([
                'assessor_pegawai_uuid' => 'The assigned Pejabat Penilai is no longer eligible and allowed.',
            ]);
        }

        return $assessor;
    }

    /**
     * @return list<array{title: ?string, activities: list<array<string, mixed>>}>
     */
    private function storedActivityTree(string $revisionUuid): array
    {
        $indicators = DB::table('pkp_indicators')
            ->where('revision_uuid', $revisionUuid)
            ->orderBy('position')
            ->get();

        return $indicators->map(function (object $indicator): array {
            return [
                'title' => $indicator->title,
                'activities' => DB::table('pkp_activities')
                    ->where('indicator_uuid', $indicator->uuid)
                    ->orderBy('position')
                    ->get(['description', 'unit', 'target_credit', 'target_quantity', 'target_quality', 'realized_quantity'])
                    ->map(function (object $activity): array {
                        $values = (array) $activity;
                        foreach (['target_credit', 'target_quantity', 'target_quality', 'realized_quantity'] as $field) {
                            $values[$field] = $values[$field] === null ? null : (string) BigDecimal::of((string) $values[$field])->stripTrailingZeros();
                        }

                        return $values;
                    })
                    ->all(),
            ];
        })->all();
    }

    /**
     * @param  list<array{title: ?string, activities: list<array<string, mixed>>}>  $indicators
     */
    private function calculateCompleteApplication(array $indicators): CalculationResult
    {
        try {
            return $this->calculator->calculate(array_map(
                fn (array $indicator): IndicatorInput => new IndicatorInput(
                    $indicator['title'],
                    array_map(fn (array $activity): ActivityInput => new ActivityInput(
                        $activity['description'] === null ? null : (string) $activity['description'],
                        $activity['unit'] === null ? null : (string) $activity['unit'],
                        $activity['target_credit'] === null ? null : (string) $activity['target_credit'],
                        $activity['target_quantity'] === null ? null : (string) $activity['target_quantity'],
                        $activity['target_quality'] === null ? null : (string) $activity['target_quality'],
                        $activity['realized_quantity'] === null ? null : (string) $activity['realized_quantity'],
                    ), $indicator['activities'])
                ),
                $indicators
            ));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['indicators' => $exception->getMessage()]);
        }
    }

    private function deadlineFor(int $year, int $month): object
    {
        $deadline = DB::table('pkp_deadlines')
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->first();

        if ($deadline !== null) {
            return $deadline;
        }

        $now = now();
        DB::table('pkp_deadlines')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'year' => $year,
            'month' => $month,
            'deadline_at' => null,
            'version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('pkp_deadlines')
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->first();
    }

    private function classifyLateness(CarbonImmutable $submittedAt, ?string $deadlineAt): string
    {
        if ($deadlineAt === null) {
            return 'deadline_not_set';
        }

        return $submittedAt->lessThanOrEqualTo(CarbonImmutable::parse($deadlineAt, 'UTC')) ? 'on_time' : 'late';
    }

    /**
     * @param  list<array{title?: ?string, activities: list<array<string, ?string>>}>  $indicators
     */
    private function replaceActivityTree(string $revisionUuid, array $indicators, mixed $now): void
    {
        DB::table('pkp_indicators')->where('revision_uuid', $revisionUuid)->delete();

        foreach ($indicators as $indicatorPosition => $indicator) {
            $indicatorUuid = (string) Str::uuid();
            DB::table('pkp_indicators')->insert([
                'uuid' => $indicatorUuid,
                'revision_uuid' => $revisionUuid,
                'title' => $indicator['title'] ?? null,
                'position' => $indicatorPosition,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($indicator['activities'] as $activityPosition => $activity) {
                DB::table('pkp_activities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'indicator_uuid' => $indicatorUuid,
                    'position' => $activityPosition,
                    'description' => $activity['description'] ?? null,
                    'unit' => $activity['unit'] ?? null,
                    'target_credit' => $activity['target_credit'] ?? null,
                    'target_quantity' => $activity['target_quantity'] ?? null,
                    'target_quality' => $activity['target_quality'] ?? null,
                    'realized_quantity' => $activity['realized_quantity'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function resolveAssessor(Pegawai $owner, ?object $settings, ?string $requestedUuid): ?string
    {
        if ($requestedUuid === null) {
            if ($settings === null || $settings->default_assessor_uuid === null) {
                return null;
            }

            $defaultUuid = $settings->default_assessor_uuid;
            $allowed = DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settings->uuid)
                ->where('assessor_pegawai_uuid', $defaultUuid)
                ->exists();

            return $allowed && $defaultUuid !== $owner->uuid && $this->isEligibleAssessor($defaultUuid)
                ? $defaultUuid
                : null;
        }

        $allowedUuids = $settings === null
            ? []
            : DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settings->uuid)
                ->pluck('assessor_pegawai_uuid')
                ->all();
        $assessorUuid = $requestedUuid;

        if ($assessorUuid === null || ! in_array($assessorUuid, $allowedUuids, true)) {
            throw ValidationException::withMessages([
                'assessor_pegawai_uuid' => 'Choose an assessor allowed for this employee.',
            ]);
        }

        if ($assessorUuid === $owner->uuid || ! $this->isEligibleAssessor($assessorUuid)) {
            throw ValidationException::withMessages([
                'assessor_pegawai_uuid' => 'Choose an active eligible assessor.',
            ]);
        }

        return $assessorUuid;
    }

    private function isEligibleAssessor(string $pegawaiUuid): bool
    {
        return Pegawai::query()
            ->whereKey($pegawaiUuid)
            ->whereHas('user', fn ($query) => $query->where('status', '1')->whereNotNull('email_verified_at'))
            ->exists();
    }
}
