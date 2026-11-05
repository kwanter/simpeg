<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfigurePkpAssessorsRequest;
use App\Http\Requests\ConfigurePkpDeadlineRequest;
use App\Http\Requests\DecidePkpRequest;
use App\Http\Requests\InspectPkpDocumentRequest;
use App\Http\Requests\ReassignPkpAssessorRequest;
use App\Http\Requests\ReassignPkpInspectorRequest;
use App\Http\Requests\RevisePkpRequest;
use App\Http\Requests\SavePkpRequest;
use App\Http\Requests\StorePkpDocumentRequest;
use App\Http\Requests\StorePkpRequest;
use App\Http\Requests\SubmitPkpRequest;
use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Services\InvalidPdf;
use App\Services\PdfValidationLimit;
use App\Services\PdfValidatorUnavailable;
use App\Services\PkpAssessmentService;
use App\Services\PkpAssessorOptionsService;
use App\Services\PkpAssessorSettingsService;
use App\Services\PkpAssignmentService;
use App\Services\PkpAuthorization;
use App\Services\PkpCalculator;
use App\Services\PkpDeadlineService;
use App\Services\PkpDocumentService;
use App\Services\PkpPdfService;
use App\Services\PkpSubmissionService;
use App\Support\ActivityInput;
use App\Support\CalculationResult;
use App\Support\ConfigurationResult;
use App\Support\ExactValue;
use App\Support\IndicatorInput;
use App\Support\PkpContext;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PkpController extends Controller
{
    public function __construct(
        private readonly PkpAuthorization $authorization,
        private readonly PkpAssessorSettingsService $assessorSettings,
        private readonly PkpAssessorOptionsService $assessorOptions,
        private readonly PkpSubmissionService $submissions,
        private readonly PkpAssessmentService $assessments,
        private readonly PkpAssignmentService $assignments,
        private readonly PkpDocumentService $documents,
        private readonly PkpDeadlineService $deadlines,
        private readonly PkpCalculator $calculator,
        private readonly PkpPdfService $pdf,
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        $filters = $request->validate([
            'year' => ['sometimes', 'integer', 'between:1900,9999'],
            'scope' => ['sometimes', 'in:own,all'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $year = (int) ($filters['year'] ?? now('Asia/Makassar')->year);
        $scope = $filters['scope'] ?? 'own';

        try {
            $items = $this->submissions->list($request->user(), $scope, $year);
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        }

        if ($request->expectsJson()) {
            return response()->json($items);
        }

        $canViewAll = $this->authorization->allows($request->user(), 'viewAll', new PkpContext);
        $canCreate = $this->authorization->allows(
            $request->user(),
            'create',
            new PkpContext(targetPegawai: $request->user()->pegawai)
        );

        return view('pkp.index', compact('items', 'year', 'scope', 'canViewAll', 'canCreate'));
    }

    public function create(Request $request): JsonResponse|View
    {
        $options = $this->assessorOptions->forOwner($request->user());
        $formData = [
            'year' => now('Asia/Makassar')->year,
            'month' => now('Asia/Makassar')->month,
            'sources' => ['application', 'external'],
            'assessor_options' => $options->eligibleOptions,
            'default_assessor_uuid' => $options->eligibleDefaultUuid,
            'settings_version' => $options->settingsVersion,
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $formData]);
        }

        return view('pkp.create', compact('formData'));
    }

    public function myAssessorOptions(Request $request): JsonResponse
    {
        $result = $this->assessorOptions->forOwner($request->user());

        return response()->json(['data' => [
            'settings_version' => $result->settingsVersion,
            'configured_default_uuid' => $result->configuredDefaultUuid,
            'default_assessor_uuid' => $result->eligibleDefaultUuid,
            'options' => $result->eligibleOptions,
        ]]);
    }

    public function assessments(Request $request): JsonResponse|View
    {
        $kind = $request->query('kind', 'content');
        if (! in_array($kind, ['content', 'document'], true)) {
            return response()->json(['error' => ['code' => 'invalid_assessment_kind', 'message' => 'Choose a content or document assessment queue.']], 422);
        }

        $page = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $items = $this->assessments->queuePage($request->user(), $kind, (int) ($page['page'] ?? 1))->appends(['kind' => $kind]);
        if ($request->expectsJson()) {
            return response()->json(['data' => $items->items(), 'meta' => [
                'total' => $items->total(), 'per_page' => $items->perPage(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(),
            ], 'links' => ['next' => $items->nextPageUrl(), 'previous' => $items->previousPageUrl()]]);
        }

        return view('pkp.assessments', compact('items', 'kind'));
    }

    public function deadline(Request $request, int $year, int $month): JsonResponse|View
    {
        $result = $this->deadlines->forPeriod($request->user(), $year, $month);
        $data = [
            'year' => $year,
            'month' => $month,
            'deadline_local' => $result->deadlineAt === null
                ? null
                : \Carbon\CarbonImmutable::instance($result->deadlineAt)->setTimezone('Asia/Makassar')->format('Y-m-d\\TH:i:s'),
            'deadline_at' => $result->deadlineAt?->format('Y-m-d H:i:s.u'),
            'version' => $result->version,
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return view('pkp.deadline', compact('data'));
    }

    public function updateDeadline(ConfigurePkpDeadlineRequest $request, int $year, int $month): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->deadlines->configure(
                $request->user(),
                $year,
                $month,
                (int) $input['expected_version'],
                $input['deadline_local'] ?? null
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return response()->json(['error' => ['code' => 'stale_configuration', 'message' => 'Reload the monthly deadline before saving.']], 409);
        }

        $deadlineLocal = $result->deadlineAt === null
            ? null
            : \Carbon\CarbonImmutable::instance($result->deadlineAt)->setTimezone('Asia/Makassar')->format('Y-m-d\\TH:i:s');
        if (! $request->expectsJson()) {
            return redirect()->route('pkp.deadline.show', [$year, $month], 303)->with('status', 'Batas waktu PKP berhasil disimpan.');
        }

        return response()->json(['data' => [
            'configuration_uuid' => $result->configurationUuid,
            'year' => $year,
            'month' => $month,
            'deadline_local' => $deadlineLocal,
            'deadline_at' => $result->deadlineAt?->format('Y-m-d H:i:s.u'),
            'version' => $result->version,
        ]]);
    }

    public function reassignAssessor(ReassignPkpAssessorRequest $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();
        try {
            $result = $this->assignments->reassignContent(
                $request->user(),
                $pkp_uuid,
                $input['revision_uuid'],
                (int) $input['expected_version'],
                $input['assessor_pegawai_uuid'],
                $input['reason']
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            if (! $request->expectsJson()) {
                return back()->withErrors($exception->errors())->withInput();
            }

            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'reassignment_conflict',
                'message' => 'The PKP assessor assignment could not be changed.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.show', $pkp_uuid, 303)->with('status', 'Pejabat Penilai PKP berhasil dialihkan.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $input['revision_uuid'],
            'assessor_pegawai_uuid' => $input['assessor_pegawai_uuid'],
            'official_revision_uuid' => $result->officialRevisionUuid,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'version' => $result->version,
        ]]);
    }

    public function reassignInspector(ReassignPkpInspectorRequest $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();
        try {
            $result = $this->assignments->reassignInspector(
                $request->user(),
                $pkp_uuid,
                $input['revision_uuid'],
                (int) $input['expected_version'],
                $input['inspector_pegawai_uuid'],
                $input['reason']
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            if (! $request->expectsJson()) {
                return back()->withErrors($exception->errors())->withInput();
            }

            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'reassignment_conflict',
                'message' => 'The PKP document inspector assignment could not be changed.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.show', $pkp_uuid, 303)->with('status', 'Pemeriksa dokumen PKP berhasil dialihkan.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $input['revision_uuid'],
            'inspector_pegawai_uuid' => $input['inspector_pegawai_uuid'],
            'official_revision_uuid' => $result->officialRevisionUuid,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'version' => $result->version,
        ]]);
    }

    public function store(StorePkpRequest $request): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        $input = $request->validated();

        try {
            $result = $this->submissions->create(
                $actor,
                (int) $input['year'],
                (int) $input['month'],
                $input['source'],
                $input['assessor_pegawai_uuid'] ?? null
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            $existing = DB::table('pkp')
                ->where('pegawai_uuid', $actor->pegawai->uuid)
                ->where('year', $input['year'])
                ->where('month', $input['month'])
                ->first();

            return response()->json(['error' => [
                'code' => 'duplicate_period',
                'message' => 'A PKP already exists for this employee and month.',
                'existing_pkp_uuid' => $existing?->uuid,
            ]], 409);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.revisions.edit', [$result->recordUuid, $result->pendingRevisionUuid], 303);
        }

        $record = DB::table('pkp')->where('uuid', $result->recordUuid)->first();
        $revision = DB::table('pkp_revisions')->where('uuid', $result->pendingRevisionUuid)->first();

        return response()->json(['data' => [
            'pkp_uuid' => $record->uuid,
            'pegawai_uuid' => $record->pegawai_uuid,
            'year' => (int) $record->year,
            'month' => (int) $record->month,
            'source' => $revision->source,
            'version' => $result->version,
            'revision_uuid' => $revision->uuid,
            'revision_number' => (int) $revision->number,
            'revision_state' => $revision->state,
        ]], 201);
    }

    public function revise(RevisePkpRequest $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->submissions->revise(
                $request->user(),
                $pkp_uuid,
                (int) $input['expected_version'],
                $input['base_revision_uuid'],
                $input['source']
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'revision_conflict',
                'message' => 'The PKP revision could not be created from its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.revisions.edit', [$result->recordUuid, $result->pendingRevisionUuid], 303);
        }

        $revision = DB::table('pkp_revisions')->where('uuid', $result->pendingRevisionUuid)->first();

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $result->pendingRevisionUuid,
            'revision_number' => (int) $revision->number,
            'official_revision_uuid' => $result->officialRevisionUuid,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'version' => $result->version,
        ]], 201);
    }

    public function editRevision(Request $request, string $pkp_uuid, string $revision_uuid): JsonResponse|View
    {
        $pkp = Pkp::query()->findOrFail($pkp_uuid);
        $revision = DB::table('pkp_revisions')
            ->where('uuid', $revision_uuid)
            ->where('pkp_uuid', $pkp->uuid)
            ->first();

        if ($revision === null) {
            abort(404, 'revision_not_found');
        }

        $owner = Pegawai::query()->findOrFail($pkp->pegawai_uuid);
        $actor = $request->user();
        $context = new PkpContext($pkp, $revision, targetPegawai: $owner);
        $this->authorization->authorize($actor, 'save', $context);

        if (! in_array($revision->state, ['draft', 'returned'], true)) {
            abort(409, 'revision_not_editable');
        }

        if ($pkp->pending_revision_uuid !== $revision_uuid) {
            abort(404, 'revision_not_found');
        }

        $indicators = $this->revisionActivityTree($revision_uuid);
        $notes = $revision->notes;
        $assessorUuid = $revision->assessor_pegawai_uuid ?? '';
        $assessorOptions = $this->assessorOptions->forOwner($actor)->eligibleOptions;
        $data = [
            'pkp_uuid' => $pkp->uuid,
            'revision_uuid' => $revision->uuid,
            'revision_number' => (int) $revision->number,
            'source' => $revision->source,
            'version' => (int) $pkp->version,
            'indicators' => $indicators,
            'notes' => $notes,
            'assessor_pegawai_uuid' => $revision->assessor_pegawai_uuid,
            'assessor_options' => $assessorOptions,
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        $scoreData = $this->scoreData($this->calculateDraft($revision, $indicators));
        $readOnly = false;
        $saved = false;
        $canDecide = false;
        $canPrint = false;
        $canSubmit = in_array($revision->state, ['draft', 'returned'], true)
            && $this->authorization->allows($actor, 'submit', $context)
            && ($revision->source !== 'external' || $revision->source_document_uuid !== null);
        $canUpload = false;
        $canUploadSource = $revision->source === 'external'
            && $pkp->pending_revision_uuid === $revision->uuid
            && in_array($revision->state, ['draft', 'returned'], true)
            && $this->authorization->allows($actor, 'documentUpload', $context);
        $canDocumentDecide = false;
        $canRefreshIdentity = false;
        $canRevise = false;
        $canReassignContent = false;
        $canReassignInspector = false;
        $assessorReassignmentOptions = [];
        $inspectorReassignmentOptions = [];
        $identitySnapshot = null;
        $currentSubmission = null;
        $documentRows = ['source' => null, 'final' => null, 'pending_final' => null];

        return view('pkp.edit', compact('pkp', 'revision', 'indicators', 'scoreData', 'readOnly', 'saved', 'canDecide', 'canPrint', 'canSubmit', 'canUpload', 'canUploadSource', 'canDocumentDecide', 'canRefreshIdentity', 'canRevise', 'canReassignContent', 'canReassignInspector', 'assessorReassignmentOptions', 'inspectorReassignmentOptions', 'identitySnapshot', 'currentSubmission', 'documentRows', 'notes', 'assessorUuid', 'assessorOptions'));
    }

    public function show(Request $request, string $pkp_uuid): JsonResponse|View
    {
        $selection = $request->validate([
            'revision_uuid' => ['required_with:submission_uuid', 'uuid'],
            'submission_uuid' => ['sometimes', 'uuid'],
        ]);
        $pkp = Pkp::query()->findOrFail($pkp_uuid);
        $owner = Pegawai::query()->findOrFail($pkp->pegawai_uuid);
        $actor = $request->user();
        $officialRead = $this->revisionRead($pkp, $owner, $pkp->official_revision_uuid);
        $pendingRead = $this->revisionRead($pkp, $owner, $pkp->pending_revision_uuid);
        $officialRead = $officialRead !== null && $this->authorization->allows($actor, 'view', $officialRead['context']) ? $officialRead : null;
        $pendingRead = $pendingRead !== null && $this->authorization->allows($actor, 'view', $pendingRead['context']) ? $pendingRead : null;
        $officialRead = $officialRead !== null && ! $this->snapshotOnlyRead($actor, $pkp, $owner, $officialRead) ? $officialRead : null;
        $pendingRead = $pendingRead !== null && ! $this->snapshotOnlyRead($actor, $pkp, $owner, $pendingRead) ? $pendingRead : null;
        if (isset($selection['revision_uuid'])) {
            $selectedRead = $this->revisionRead($pkp, $owner, $selection['revision_uuid'], $selection['submission_uuid'] ?? null);
            if ($selectedRead === null) {
                abort(404, 'revision_not_found');
            }
        } else {
            $selectedRead = $pendingRead ?? $officialRead;
            if ($selectedRead === null) {
                $ownDecision = DB::table('pkp_decisions as decision')->join('pkp_submissions as submission', 'submission.uuid', '=', 'decision.submission_uuid')
                    ->join('pkp_revisions as revision', 'revision.uuid', '=', 'submission.revision_uuid')
                    ->where('revision.pkp_uuid', $pkp->uuid)->where('decision.actor_user_uuid', $actor->uuid)
                    ->orderByDesc('revision.number')->orderByDesc('submission.number')->first(['revision.uuid as revision_uuid', 'submission.uuid as submission_uuid']);
                $selectedRead = $ownDecision === null ? null : $this->revisionRead($pkp, $owner, $ownDecision->revision_uuid, $ownDecision->submission_uuid);
            }
            if ($selectedRead === null) {
                throw new AuthorizationException('This PKP has no authorized read context.');
            }
        }
        $this->authorization->authorize($actor, 'view', $selectedRead['context']);
        $historicalView = isset($selection['submission_uuid'])
            || ! in_array($selectedRead['revision']->uuid, [$pkp->official_revision_uuid, $pkp->pending_revision_uuid], true)
            || $this->snapshotOnlyRead($actor, $pkp, $owner, $selectedRead);
        $revision = $selectedRead['revision'];
        $revisionUuid = $revision->uuid;
        $approved = $revision->state === 'approved';
        $currentSubmission = $selectedRead['submission'];
        if ($approved && ! $historicalView && ($currentSubmission === null || $currentSubmission->state !== 'approved')) {
            abort(404, 'approved_submission_not_found');
        }
        $context = new PkpContext($pkp, $revision, submission: $currentSubmission, targetPegawai: $owner);
        $this->authorization->authorize($actor, 'view', $context);
        $useSubmissionSnapshot = $currentSubmission !== null && ($historicalView || in_array($revision->state, ['approved', 'submitted'], true));
        if ($useSubmissionSnapshot) {
            $inputSnapshot = json_decode($currentSubmission->input_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $identitySnapshot = json_decode($currentSubmission->identity_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $scoreSnapshot = $currentSubmission->score_snapshot === null
                ? null
                : json_decode($currentSubmission->score_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $indicators = $inputSnapshot['indicators'] ?? [];
            $scoreData = $this->scoreDataFromSnapshot($scoreSnapshot);
        } else {
            $identitySnapshot = $currentSubmission === null
                ? null
                : json_decode($currentSubmission->identity_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $indicators = $this->revisionActivityTree($revisionUuid);
            $scoreData = $this->scoreData($this->calculateDraft($revision, $indicators));
        }
        $canDecide = ! $historicalView && $revision->state === 'submitted' && $currentSubmission?->state === 'submitted' && $this->authorization->allows($actor, 'contentDecision', $context);
        $canPrint = $this->authorization->allows($actor, 'print', $context);
        $canSubmit = ! $historicalView && in_array($revision->state, ['draft', 'returned'], true)
            && $this->authorization->allows($actor, 'submit', $context)
            && ($revision->source !== 'external' || $revision->source_document_uuid !== null);
        $canRefreshIdentity = ! $historicalView && $revision->state === 'submitted'
            && $this->authorization->allows($actor, 'refreshIdentity', $context);
        $canRevise = ! $historicalView && $pkp->pending_revision_uuid === null
            && $pkp->official_revision_uuid === $revision->uuid
            && $revision->state === 'approved'
            && $this->authorization->allows($actor, 'revise', $context);
        $canUpload = ! $historicalView && $revision->state === 'approved'
            && $pkp->official_revision_uuid === $revision->uuid
            && $this->authorization->allows($actor, 'documentUpload', $context);
        $canUploadSource = ! $historicalView && $revision->source === 'external'
            && $pkp->pending_revision_uuid === $revision->uuid
            && in_array($revision->state, ['draft', 'returned'], true)
            && $this->authorization->allows($actor, 'documentUpload', $context);
        $documentRows = [
            'source' => $this->documentData($useSubmissionSnapshot ? $currentSubmission->source_document_uuid : $revision->source_document_uuid),
            'final' => $historicalView ? null : $this->documentData($revision->final_document_uuid),
            'pending_final' => $historicalView ? null : $this->documentData($revision->pending_final_document_uuid),
        ];
        $pendingFinal = $revision->pending_final_document_uuid === null
            ? null
            : DB::table('pkp_documents')->where('uuid', $revision->pending_final_document_uuid)->first();
        $canDocumentDecide = ! $historicalView && $pendingFinal !== null && $this->authorization->allows(
            $actor,
            'documentDecision',
            new PkpContext($pkp, $revision, candidate: $pendingFinal, submission: $currentSubmission, targetPegawai: $owner)
        );
        $assessorReassignmentOptions = [];
        $inspectorReassignmentOptions = [];
        $canReassignContent = ! $historicalView && $this->authorization->allows($actor, 'reassignContent', new PkpContext($pkp, $revision))
            && $pkp->pending_revision_uuid === $revision->uuid
            && in_array($revision->state, ['submitted', 'returned'], true);
        $canReassignInspector = ! $historicalView && $this->authorization->allows($actor, 'reassignInspector', new PkpContext($pkp, $revision))
            && $pkp->official_revision_uuid === $revision->uuid
            && $revision->state === 'approved';
        if ($canReassignContent || $canReassignInspector) {
            $allowedOptions = $this->reassignmentOptions($owner->uuid);
            $assessorReassignmentOptions = array_values(array_filter(
                $allowedOptions,
                fn (array $option): bool => $option['pegawai_uuid'] !== $revision->assessor_pegawai_uuid
            ));
            $inspectorReassignmentOptions = array_values(array_filter(
                $allowedOptions,
                fn (array $option): bool => $option['pegawai_uuid'] !== $revision->inspector_pegawai_uuid
            ));
            $canReassignContent = $canReassignContent && $assessorReassignmentOptions !== [];
            $canReassignInspector = $canReassignInspector && $inspectorReassignmentOptions !== [];
        }
        $readOnly = $historicalView || ! ($this->authorization->allows($actor, 'save', $context)
            && in_array($revision->state, ['draft', 'returned'], true));
        $notes = $useSubmissionSnapshot ? ($inputSnapshot['notes'] ?? null) : $revision->notes;
        $assessorUuid = $revision->assessor_pegawai_uuid ?? '';
        $assessorOptions = $readOnly ? [] : $this->assessorOptions->forOwner($actor)->eligibleOptions;
        $saved = $request->query('saved') === '1';
        $officialData = $officialRead === null ? null : $this->revisionReadData($actor, $pkp, $owner, $officialRead);
        $pendingData = $pendingRead === null ? null : $this->revisionReadData($actor, $pkp, $owner, $pendingRead);
        $history = $this->historyData($actor, $pkp, $owner);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'pkp_uuid' => $pkp->uuid,
                'pegawai_uuid' => $pkp->pegawai_uuid,
                'year' => $pkp->year,
                'month' => $pkp->month,
                'version' => $pkp->version,
                'official_revision_uuid' => $officialData['revision_uuid'] ?? null,
                'pending_revision_uuid' => $pendingData['revision_uuid'] ?? null,
                'official' => $officialData,
                'pending' => $pendingData,
                'historical' => $historicalView,
                'first_submitted_at' => $pkp->getRawOriginal('first_submitted_at'),
                'deadline_snapshot_at' => $pkp->getRawOriginal('deadline_snapshot_at'),
                'lateness' => $pkp->lateness,
                'history' => $history,
                'revision_uuid' => $revision->uuid,
                'revision_number' => (int) $revision->number,
                'revision_state' => $revision->state,
                'submission_uuid' => $currentSubmission->uuid ?? null,
                'source' => $revision->source,
                'indicators' => $indicators,
                'notes' => $notes,
                'assessor_pegawai_uuid' => $revision->assessor_pegawai_uuid,
                'assessor_options' => $assessorOptions,
                'score' => $scoreData,
                'identity_snapshot' => $identitySnapshot,
                'capabilities' => ['print' => $canPrint, 'submit' => $canSubmit, 'revise' => $canRevise, 'refresh_identity' => $canRefreshIdentity, 'upload_final' => $canUpload, 'upload_source' => $canUploadSource, 'decide' => $canDecide, 'inspect_document' => $canDocumentDecide, 'reassign_content' => $canReassignContent, 'reassign_inspector' => $canReassignInspector],
                'documents' => $documentRows,
            ]]);
        }

        return view('pkp.edit', compact('pkp', 'revision', 'indicators', 'scoreData', 'readOnly', 'saved', 'canDecide', 'canPrint', 'canSubmit', 'canUpload', 'canUploadSource', 'canDocumentDecide', 'canRefreshIdentity', 'canRevise', 'canReassignContent', 'canReassignInspector', 'assessorReassignmentOptions', 'inspectorReassignmentOptions', 'identitySnapshot', 'currentSubmission', 'documentRows', 'notes', 'assessorUuid', 'assessorOptions', 'officialData', 'pendingData', 'history', 'historicalView'));
    }

    public function pdf(Request $request, string $pkp_uuid, string $revision_uuid): Response|StreamedResponse
    {
        return $this->pdf->render($request->user(), $pkp_uuid, $revision_uuid);
    }

    public function downloadDocument(Request $request, string $pkp_uuid, string $document_uuid): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->documents->download($request->user(), $pkp_uuid, $document_uuid);
    }

    public function decideDocument(InspectPkpDocumentRequest $request, string $pkp_uuid, string $document_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->documents->decide(
                $request->user(),
                $pkp_uuid,
                $document_uuid,
                $input['revision_uuid'],
                (int) $input['expected_version'],
                $input['decision'],
                $input['reason'] ?? null,
                array_key_exists('matches_approved_content', $input) ? (bool) $input['matches_approved_content'] : null
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'document_decision_conflict',
                'message' => 'The document cannot be reviewed in its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.assessments', ['kind' => 'document'], 303)
                ->with('status', $input['decision'] === 'accept' ? 'Dokumen final diterima.' : 'Dokumen final dikembalikan.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'document_uuid' => $result->documentUuid,
            'official_revision_uuid' => $result->officialRevisionUuid,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'version' => $result->version,
            'completeness' => $result->completeness,
        ]]);
    }

    public function storeDocument(StorePkpDocumentRequest $request, string $pkp_uuid, string $revision_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->documents->store(
                $request->user(),
                $pkp_uuid,
                $revision_uuid,
                (int) $input['expected_version'],
                $input['kind'],
                $input['file'],
                (bool) ($input['declared_signed'] ?? false),
                array_key_exists('content_unchanged', $input) ? (bool) $input['content_unchanged'] : null
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (InvalidPdf) {
            return response()->json(['error' => ['code' => 'invalid_pdf', 'message' => 'The uploaded file is not a readable PDF.']], 422);
        } catch (PdfValidationLimit) {
            return response()->json(['error' => ['code' => 'pdf_validation_limit', 'message' => 'The PDF exceeds the validation resource limits.']], 422);
        } catch (PdfValidatorUnavailable) {
            return response()->json(['error' => ['code' => 'pdf_validator_unavailable', 'message' => 'PDF validation is temporarily unavailable.']], 503);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409, 503], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'document_upload_failed',
                'message' => 'The document could not be uploaded in its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.show', $pkp_uuid, 303)->with('status', 'Dokumen berhasil diunggah dan menunggu pemeriksaan.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $revision_uuid,
            'document_uuid' => $result->documentUuid,
            'version' => $result->version,
            'completeness' => $result->completeness,
        ]], 201);
    }

    public function updateRevision(SavePkpRequest $request, string $pkp_uuid, string $revision_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->submissions->save(
                $request->user(),
                $pkp_uuid,
                $revision_uuid,
                (int) $input['expected_version'],
                $input
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            $code = match ($exception->getStatusCode()) {
                404 => 'revision_not_found',
                default => $exception->getMessage() ?: 'revision_conflict',
            };

            return response()->json(['error' => ['code' => $code, 'message' => 'The PKP revision could not be saved in its current state.']], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.revisions.edit', [$pkp_uuid, $revision_uuid], 303)
                ->with('status', 'Perubahan tersimpan.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'version' => $result->version,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'redirect_url' => route('pkp.show', ['pkp_uuid' => $pkp_uuid, 'saved' => 1]),
        ]]);
    }

    public function submit(SubmitPkpRequest $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->submissions->submit(
                $request->user(),
                $pkp_uuid,
                $input['revision_uuid'],
                (int) $input['expected_version'],
                now('UTC')->toDateTimeImmutable()
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'submission_conflict',
                'message' => 'The PKP submission could not be completed in its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.show', $pkp_uuid, 303)->with('status', 'PKP berhasil diajukan kepada Pejabat Penilai.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $result->pendingRevisionUuid,
            'submission_uuid' => $result->submissionUuid,
            'state' => 'submitted',
            'version' => $result->version,
            'lateness' => $result->lateness,
        ]]);
    }

    public function refreshIdentity(Request $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'revision_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:5000'],
            'actor_user_uuid' => ['prohibited'],
            'identity_snapshot' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'lateness' => ['prohibited'],
        ]);

        try {
            $result = $this->submissions->refreshIdentity(
                $request->user(),
                $pkp_uuid,
                $input['revision_uuid'],
                (int) $input['expected_version'],
                $input['reason'],
                now('UTC')->toDateTimeImmutable()
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (ValidationException $exception) {
            return $this->validationErrorResponse($exception);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'identity_refresh_conflict',
                'message' => 'The PKP identity cannot be refreshed in its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.revisions.edit', [$pkp_uuid, $input['revision_uuid']], 303)
                ->with('status', 'Identitas diperbarui. Periksa kembali draf dan kirim ulang.');
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $result->pendingRevisionUuid,
            'submission_uuid' => $result->submissionUuid,
            'state' => 'returned',
            'version' => $result->version,
            'lateness' => $result->lateness,
        ]]);
    }

    public function decide(DecidePkpRequest $request, string $pkp_uuid): JsonResponse|RedirectResponse
    {
        $input = $request->validated();

        try {
            $result = $this->assessments->decide(
                $request->user(),
                $pkp_uuid,
                $input['revision_uuid'],
                $input['submission_uuid'],
                (int) $input['expected_version'],
                $input['decision'],
                $input['reason'] ?? null,
                now('UTC')->toDateTimeImmutable()
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }

            return response()->json(['error' => [
                'code' => $exception->getMessage() ?: 'decision_conflict',
                'message' => 'The PKP submission could not be decided in its current state.',
            ]], $exception->getStatusCode());
        }

        if (! $request->expectsJson()) {
            return redirect()->route('pkp.assessments', ['kind' => 'content'], 303)
                ->with('status', 'Keputusan PKP berhasil disimpan.')
                ->with('decision_pkp_uuid', $pkp_uuid);
        }

        return response()->json(['data' => [
            'pkp_uuid' => $result->recordUuid,
            'revision_uuid' => $result->officialRevisionUuid ?? $result->pendingRevisionUuid,
            'official_revision_uuid' => $result->officialRevisionUuid,
            'pending_revision_uuid' => $result->pendingRevisionUuid,
            'submission_uuid' => $result->submissionUuid,
            'state' => $input['decision'] === 'approve' ? 'approved' : 'returned',
            'version' => $result->version,
        ]]);
    }

    private function fullRecordRead(User $actor, Pkp $pkp, Pegawai $owner): bool
    {
        return $this->authorization->allows($actor, 'save', new PkpContext($pkp, targetPegawai: $owner))
            || $this->authorization->allows($actor, 'viewAll', new PkpContext($pkp, targetPegawai: $owner));
    }

    private function snapshotOnlyRead(User $actor, Pkp $pkp, Pegawai $owner, array $read): bool
    {
        if ($this->fullRecordRead($actor, $pkp, $owner)) {
            return false;
        }
        $revision = $read['revision'];
        $submission = $read['submission'];
        $pegawaiUuid = $actor->pegawai?->uuid;

        return ! (($revision->state === 'submitted' && $revision->assessor_pegawai_uuid === $pegawaiUuid
            && $submission?->uuid === $revision->current_submission_uuid && $submission?->state === 'submitted')
            || ($revision->state === 'approved' && $revision->inspector_pegawai_uuid === $pegawaiUuid
                && $submission?->uuid === $revision->approved_submission_uuid && $submission?->state === 'approved'));
    }

    private function historyData(User $actor, Pkp $pkp, Pegawai $owner): array
    {
        $full = $this->fullRecordRead($actor, $pkp, $owner);
        $revisions = DB::table('pkp_revisions')->where('pkp_uuid', $pkp->uuid)->orderBy('number')->get()->keyBy('uuid');
        $attempts = DB::table('pkp_submissions as submission')->join('pkp_revisions as revision', 'revision.uuid', '=', 'submission.revision_uuid')
            ->where('revision.pkp_uuid', $pkp->uuid)->orderBy('revision.number')->orderBy('submission.number')->get(['submission.*'])
            ->filter(fn (object $submission): bool => $full || $this->authorization->allows($actor, 'view', new PkpContext($pkp, $revisions[$submission->revision_uuid], submission: $submission, targetPegawai: $owner)))->values();
        $attemptIds = $attempts->pluck('uuid')->all();
        $revisionIds = $full ? $revisions->keys()->all() : $attempts->pluck('revision_uuid')->unique()->values()->all();
        $inspectedRevisionIds = $revisions->filter(fn (object $row): bool => $row->state === 'approved' && $row->inspector_pegawai_uuid === $actor->pegawai?->uuid)->keys()->all();
        $sourceIds = $attempts->pluck('source_document_uuid')->filter()->values()->all();
        $documents = DB::table('pkp_documents')->whereIn('revision_uuid', $revisionIds)
            ->when(! $full, fn ($query) => $query->where(fn ($scope) => $scope->whereIn('uuid', $sourceIds)->orWhereIn('revision_uuid', $inspectedRevisionIds)))
            ->orderBy('uploaded_at')->orderBy('uuid')->get(['uuid', 'revision_uuid', 'kind', 'original_name', 'detected_mime', 'byte_size', 'sha256', 'page_count', 'validator_version', 'declared_signed', 'uploaded_at', 'uploaded_by_user_uuid', 'replacement_of_document_uuid', 'content_unchanged_declared']);
        $decisions = DB::table('pkp_decisions')->whereIn('submission_uuid', $attemptIds)->orderBy('decided_at')->get();
        $reviews = DB::table('pkp_document_reviews')->whereIn('document_uuid', $documents->pluck('uuid'))->orderBy('decided_at')->get();
        $events = DB::table('pkp_events')->where('pkp_uuid', $pkp->uuid)
            ->when(! $full, fn ($query) => $query->whereIn('revision_uuid', $revisionIds)->where('actor_user_uuid', $actor->uuid))
            ->orderBy('occurred_at')->orderBy('uuid')->get()
            ->map(fn (object $row): array => ['uuid' => $row->uuid, 'revision_uuid' => $row->revision_uuid, 'actor_user_uuid' => $row->actor_user_uuid, 'event_type' => $row->event_type,
                'before' => $row->before_json === null ? null : json_decode($row->before_json, true, 512, JSON_THROW_ON_ERROR),
                'after' => $row->after_json === null ? null : json_decode($row->after_json, true, 512, JSON_THROW_ON_ERROR), 'occurred_at' => $row->occurred_at]);

        return [
            'revisions' => $revisions->filter(fn (object $row): bool => in_array($row->uuid, $revisionIds, true))->values()->map(fn (object $row): array => ['uuid' => $row->uuid, 'number' => (int) $row->number, 'source' => $row->source])->all(),
            'attempts' => $attempts->map(fn (object $row): array => ['uuid' => $row->uuid, 'revision_uuid' => $row->revision_uuid, 'revision_number' => (int) $revisions[$row->revision_uuid]->number, 'number' => (int) $row->number, 'state' => $row->state,
                'input_snapshot' => json_decode($row->input_snapshot, true, 512, JSON_THROW_ON_ERROR), 'identity_snapshot' => json_decode($row->identity_snapshot, true, 512, JSON_THROW_ON_ERROR),
                'assessor_snapshot' => json_decode($row->assessor_snapshot, true, 512, JSON_THROW_ON_ERROR), 'score_snapshot' => $row->score_snapshot === null ? null : json_decode($row->score_snapshot, true, 512, JSON_THROW_ON_ERROR),
                'payload_sha256' => $row->payload_sha256, 'identity_sha256' => $row->identity_sha256, 'source_document_uuid' => $row->source_document_uuid, 'submitted_at' => $row->submitted_at])->all(),
            'decisions' => $decisions->map(fn (object $row): array => (array) $row)->all(),
            'document_reviews' => $reviews->map(fn (object $row): array => (array) $row)->all(),
            'documents' => $documents->map(fn (object $row): array => (array) $row)->all(), 'events' => $events->all(),
        ];
    }

    private function revisionRead(Pkp $pkp, Pegawai $owner, ?string $revisionUuid, ?string $submissionUuid = null): ?array
    {
        if ($revisionUuid === null) {
            return null;
        }
        $revision = DB::table('pkp_revisions')->where('uuid', $revisionUuid)->where('pkp_uuid', $pkp->uuid)->first();
        if ($revision === null) {
            return null;
        }
        $uuid = $submissionUuid ?? ($revision->state === 'approved' ? $revision->approved_submission_uuid : $revision->current_submission_uuid);
        $submission = $uuid === null ? null : DB::table('pkp_submissions')->where('uuid', $uuid)->where('revision_uuid', $revision->uuid)->first();
        if ($submissionUuid !== null && $submission === null) {
            return null;
        }

        return ['revision' => $revision, 'submission' => $submission, 'context' => new PkpContext($pkp, $revision, submission: $submission, targetPegawai: $owner)];
    }

    private function revisionReadData(User $actor, Pkp $pkp, Pegawai $owner, array $read): array
    {
        $revision = $read['revision'];
        $submission = $read['submission'];
        $frozen = $submission !== null && in_array($revision->state, ['approved', 'submitted'], true);
        $input = $frozen ? json_decode($submission->input_snapshot, true, 512, JSON_THROW_ON_ERROR) : ['notes' => $revision->notes, 'indicators' => $this->revisionActivityTree($revision->uuid)];
        $score = $frozen
            ? $this->scoreDataFromSnapshot($submission->score_snapshot === null ? null : json_decode($submission->score_snapshot, true, 512, JSON_THROW_ON_ERROR))
            : $this->scoreData($this->calculateDraft($revision, $input['indicators']));
        $approved = $revision->state === 'approved' && $pkp->official_revision_uuid === $revision->uuid;
        $editable = $pkp->pending_revision_uuid === $revision->uuid && in_array($revision->state, ['draft', 'returned'], true);
        $candidate = $revision->pending_final_document_uuid === null ? null : DB::table('pkp_documents')->where('uuid', $revision->pending_final_document_uuid)->where('revision_uuid', $revision->uuid)->first();

        return [
            'revision_uuid' => $revision->uuid, 'revision_number' => (int) $revision->number, 'revision_state' => $revision->state,
            'source' => $revision->source, 'submission_uuid' => $submission->uuid ?? null, 'notes' => $input['notes'] ?? null,
            'content_approved' => $approved,
            'completeness' => $approved
                ? ($revision->final_document_uuid !== null ? 'complete' : ($revision->pending_final_document_uuid !== null ? 'awaiting_inspection' : 'awaiting_signed_upload'))
                : ($revision->state === 'submitted' ? 'awaiting_content_approval' : 'incomplete'),
            'indicators' => $input['indicators'], 'score' => $score,
            'identity_snapshot' => $submission === null ? null : json_decode($submission->identity_snapshot, true, 512, JSON_THROW_ON_ERROR),
            'documents' => ['source' => $this->documentData($revision->source_document_uuid), 'final' => $this->documentData($revision->final_document_uuid), 'pending_final' => $this->documentData($revision->pending_final_document_uuid)],
            'capabilities' => [
                'save' => $editable && $this->authorization->allows($actor, 'save', $read['context']),
                'submit' => $editable && ($revision->source !== 'external' || $revision->source_document_uuid !== null) && $this->authorization->allows($actor, 'submit', $read['context']),
                'print' => $this->authorization->allows($actor, 'print', $read['context']),
                'upload_final' => $approved && $this->authorization->allows($actor, 'documentUpload', $read['context']),
                'upload_source' => $editable && $revision->source === 'external' && $this->authorization->allows($actor, 'documentUpload', $read['context']),
                'decide' => $revision->state === 'submitted' && $this->authorization->allows($actor, 'contentDecision', $read['context']),
                'refresh_identity' => $revision->state === 'submitted' && $this->authorization->allows($actor, 'refreshIdentity', $read['context']),
                'revise' => $approved && $pkp->pending_revision_uuid === null && $this->authorization->allows($actor, 'revise', $read['context']),
                'inspect_document' => $approved && $candidate !== null && $this->authorization->allows($actor, 'documentDecision', new PkpContext($pkp, $revision, candidate: $candidate, submission: $submission, targetPegawai: $owner)),
                'reassign_content' => $pkp->pending_revision_uuid === $revision->uuid && in_array($revision->state, ['submitted', 'returned'], true) && $this->authorization->allows($actor, 'reassignContent', $read['context']),
                'reassign_inspector' => $approved && $this->authorization->allows($actor, 'reassignInspector', $read['context']),
            ],
        ];
    }

    /** @return list<array{title: ?string, activities: list<array<string, mixed>>}> */
    private function revisionActivityTree(string $revisionUuid): array
    {
        $indicators = DB::table('pkp_indicators')
            ->where('revision_uuid', $revisionUuid)
            ->orderBy('position')
            ->get();

        return $indicators->map(function (object $indicator): array {
            $activities = DB::table('pkp_activities')
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
                ->all();

            return ['title' => $indicator->title, 'activities' => $activities];
        })->all();
    }

    /** @return array<string, mixed>|null */
    private function documentData(?string $documentUuid): ?array
    {
        if ($documentUuid === null) {
            return null;
        }

        $document = DB::table('pkp_documents')->where('uuid', $documentUuid)->first([
            'uuid',
            'kind',
            'original_name',
            'byte_size',
            'sha256',
            'page_count',
            'uploaded_at',
        ]);

        return $document === null ? null : (array) $document;
    }

    /**
     * @param  list<array{title: ?string, activities: list<array<string, mixed>>}>  $indicators
     */
    private function calculateDraft(object $revision, array $indicators): ?CalculationResult
    {
        if ($revision->source !== 'application' || $indicators === []) {
            return null;
        }

        $inputs = [];
        foreach ($indicators as $indicator) {
            $activities = [];
            foreach ($indicator['activities'] as $activity) {
                $activities[] = new ActivityInput(
                    $this->nullableString($activity['description'] ?? null),
                    $this->nullableString($activity['unit'] ?? null),
                    $this->nullableString($activity['target_credit'] ?? null),
                    $this->nullableString($activity['target_quantity'] ?? null),
                    $this->nullableString($activity['target_quality'] ?? null),
                    $this->nullableString($activity['realized_quantity'] ?? null),
                );
            }
            $inputs[] = new IndicatorInput($indicator['title'], $activities);
        }

        try {
            return $this->calculator->calculate($inputs);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function scoreData(?CalculationResult $result): ?array
    {
        if ($result === null) {
            return null;
        }

        return [
            'calculation_version' => $result->version,
            'overall_score' => $this->exactValueData($result->overallScore),
            'predicate' => $result->predicate,
            'indicators' => array_map(function (array $indicator): array {
                return [
                    'title' => $indicator['title'],
                    'score' => $this->exactValueData($indicator['score']),
                    'activities' => array_map(fn (array $activity): array => [
                        'description' => $activity['description'],
                        'unit' => $activity['unit'],
                        'realized_credit' => $this->exactValueData($activity['realizedCredit']),
                        'realized_quality' => $this->exactValueData($activity['realizedQuality']),
                        'achievement' => $this->exactValueData($activity['achievement']),
                    ], $indicator['activities']),
                ];
            }, $result->indicators),
        ];
    }

    /**
     * @return array{numerator: string, denominator: string, display: string, repeating: bool}
     */
    private function exactValueData(ExactValue $value): array
    {
        return [
            'numerator' => $value->numerator,
            'denominator' => $value->denominator,
            'display' => $value->display,
            'repeating' => $value->repeating,
        ];
    }

    private function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        $fields = $exception->errors();
        $messages = array_merge(...array_values($fields));
        $code = match (true) {
            in_array('period_not_submittable', $messages, true) => 'period_not_submittable',
            in_array('content_revision_required', $messages, true) => 'content_revision_required',
            in_array('content_not_confirmed', $messages, true) => 'content_not_confirmed',
            default => 'validation_failed',
        };

        if ($code === 'period_not_submittable') {
            $fields['period'] = ['A future monthly PKP cannot be submitted.'];
        }

        return response()->json(['error' => [
            'code' => $code,
            'message' => match ($code) {
                'period_not_submittable' => 'A future monthly PKP cannot be submitted.',
                'content_revision_required' => 'Upload changed content as a new PKP revision before replacing the final document.',
                'content_not_confirmed' => 'Confirm that the document matches the approved PKP content.',
                default => 'The submitted PKP data is invalid.',
            },
            'fields' => $fields,
        ]], 422);
    }

    /**
     * @return list<array{pegawai_uuid: string, name: string, nip: ?string}>
     */
    private function reassignmentOptions(string $ownerPegawaiUuid): array
    {
        return DB::table('pkp_assessor_settings as settings')
            ->join('pkp_allowed_assessors as allowed', 'allowed.settings_uuid', '=', 'settings.uuid')
            ->join('pegawai as employee', 'employee.uuid', '=', 'allowed.assessor_pegawai_uuid')
            ->join('users', 'users.uuid', '=', 'employee.user_uuid')
            ->where('settings.pegawai_uuid', $ownerPegawaiUuid)
            ->where('employee.uuid', '<>', $ownerPegawaiUuid)
            ->whereNull('employee.deleted_at')
            ->whereNull('users.deleted_at')
            ->where('users.status', '1')
            ->whereNotNull('users.email_verified_at')
            ->orderBy('employee.nama')
            ->get([
                'employee.uuid as pegawai_uuid',
                'employee.nama as name',
                'employee.nip',
            ])
            ->map(fn (object $row): array => [
                'pegawai_uuid' => $row->pegawai_uuid,
                'name' => $row->name,
                'nip' => $row->nip,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    private function scoreDataFromSnapshot(?array $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        return [
            'calculation_version' => $snapshot['version'],
            'overall_score' => $snapshot['overall_score'],
            'predicate' => $snapshot['predicate'],
            'indicators' => $snapshot['indicators'],
        ];
    }

    public function assessorSettings(Request $request, string $pegawai_uuid): JsonResponse|View
    {
        $pegawai = Pegawai::query()->findOrFail($pegawai_uuid);
        $actor = $request->user();

        try {
            $this->authorization->authorize($actor, 'manageAssessorSettings', new PkpContext(targetPegawai: $pegawai));
        } catch (AuthorizationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
            }

            throw $exception;
        }

        $settings = DB::table('pkp_assessor_settings')->where('pegawai_uuid', $pegawai->uuid)->first();
        $allowedAssessorUuids = $settings === null
            ? []
            : DB::table('pkp_allowed_assessors')
                ->where('settings_uuid', $settings->uuid)
                ->orderBy('assessor_pegawai_uuid')
                ->pluck('assessor_pegawai_uuid')
                ->all();

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'settings_version' => (int) ($settings->version ?? 0),
                'configured_default_uuid' => $settings->default_assessor_uuid ?? null,
                'allowed_assessor_uuids' => $allowedAssessorUuids,
            ]]);
        }

        $assessors = Pegawai::query()
            ->whereKeyNot($pegawai->uuid)
            ->whereHas('user', fn ($query) => $query->where('status', '1')->whereNotNull('email_verified_at'))
            ->orderBy('nama')
            ->get();

        return view('pkp.assessor-settings', compact('pegawai', 'settings', 'allowedAssessorUuids', 'assessors'));
    }

    public function updateAssessorSettings(ConfigurePkpAssessorsRequest $request, string $pegawai_uuid): JsonResponse|RedirectResponse
    {
        $pegawai = Pegawai::query()->findOrFail($pegawai_uuid);
        $actor = $request->user();

        try {
            $this->authorization->authorize($actor, 'manageAssessorSettings', new PkpContext(targetPegawai: $pegawai));
            $input = $request->validated();
            $result = $this->assessorSettings->configure(
                $actor,
                $pegawai->uuid,
                (int) $input['expected_version'],
                $input['default_assessor_uuid'] ?? null,
                array_values($input['allowed_assessor_uuids'])
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => $exception->getMessage()]], 403);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return response()->json(['error' => ['code' => 'stale_configuration', 'message' => 'The assessor configuration has changed. Reload it before saving.']], 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->configurationData($result)]);
        }

        return response()->redirectToRoute('pkp.assessor-settings.show', $pegawai->uuid, 303);
    }

    /**
     * @return array{configuration_uuid: string, settings_version: int, configured_default_uuid: ?string, allowed_assessor_uuids: list<string>}
     */
    private function configurationData(ConfigurationResult $result): array
    {
        return [
            'configuration_uuid' => $result->configurationUuid,
            'settings_version' => $result->version,
            'configured_default_uuid' => $result->defaultAssessorUuid,
            'allowed_assessor_uuids' => $result->allowedAssessorUuids,
        ];
    }
}
