<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Support\PkpContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PkpDocumentService
{
    private const DISK = 'local';

    private const DIRECTORY = 'pkp/documents';

    public function __construct(
        private readonly PkpAuthorization $authorization,
        private readonly PkpPdfValidator $validator,
    ) {}

    public function store(
        User $actor,
        string $pkpUuid,
        string $revisionUuid,
        int $expectedVersion,
        string $kind,
        UploadedFile $file,
        bool $declaredSigned = false,
        ?bool $contentUnchanged = null,
    ): PkpMutationResult {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($actor, $pkpUuid, $revisionUuid, $expectedVersion, $kind, $file, $declaredSigned, $contentUnchanged, &$storedPath): PkpMutationResult {
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

                $this->authorization->authorize($actor, 'documentUpload', new PkpContext($pkp, $revision, targetPegawai: $owner));

                if ($expectedVersion !== $pkp->version) {
                    abort(409, 'stale_version');
                }

                $replacementOf = null;
                if ($kind === 'source') {
                    if ($revision->source !== 'external'
                        || $pkp->pending_revision_uuid !== $revisionUuid
                        || ! in_array($revision->state, ['draft', 'returned'], true)
                    ) {
                        abort(409, 'source_upload_not_allowed');
                    }
                    $replacementOf = $revision->source_document_uuid;
                } elseif ($kind === 'signed_final') {
                    if ($pkp->official_revision_uuid !== $revisionUuid || $revision->state !== 'approved') {
                        abort(409, 'final_upload_requires_approved_revision');
                    }
                    if ($revision->pending_final_document_uuid !== null) {
                        abort(409, 'pending_document_exists');
                    }
                    $replacementOf = $revision->final_document_uuid;
                    if ($replacementOf !== null && $contentUnchanged !== true) {
                        throw ValidationException::withMessages([
                            'content_unchanged' => ['content_revision_required'],
                        ]);
                    }
                } else {
                    throw ValidationException::withMessages(['kind' => ['Choose a source or signed final PDF.']]);
                }

                $documentUuid = (string) Str::uuid();
                $storedPath = self::DIRECTORY.'/'.$documentUuid.'.pdf';
                $stored = $file->storeAs(self::DIRECTORY, $documentUuid.'.pdf', self::DISK);
                if ($stored === false) {
                    $storedPath = null;
                    abort(503, 'document_storage_failed');
                }

                $inspection = $this->validator->inspect(Storage::disk(self::DISK)->path($stored));
                $now = now('UTC');
                $originalName = basename($file->getClientOriginalName());
                DB::table('pkp_documents')->insert([
                    'uuid' => $documentUuid,
                    'revision_uuid' => $revisionUuid,
                    'kind' => $kind,
                    'storage_path' => $stored,
                    'original_name' => mb_substr($originalName, 0, 255),
                    'detected_mime' => $inspection->detectedMime,
                    'byte_size' => $inspection->byteSize,
                    'sha256' => $inspection->sha256,
                    'page_count' => $inspection->pageCount,
                    'validator_version' => $inspection->validatorVersion,
                    'declared_signed' => $kind === 'source' ? $declaredSigned : false,
                    'uploaded_by_user_uuid' => $actor->uuid,
                    'uploaded_at' => $now,
                    'replacement_of_document_uuid' => $replacementOf,
                    'content_unchanged_declared' => $replacementOf === null ? null : $contentUnchanged,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $nextVersion = $expectedVersion + 1;
                $bindingColumn = $kind === 'source' ? 'source_document_uuid' : 'pending_final_document_uuid';
                DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                    $bindingColumn => $documentUuid,
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
                    'event_type' => $kind === 'source' ? 'source_document_uploaded' : 'signed_final_uploaded',
                    'before_json' => json_encode(['version' => $expectedVersion], JSON_THROW_ON_ERROR),
                    'after_json' => json_encode(['document_uuid' => $documentUuid, 'sha256' => $inspection->sha256, 'byte_size' => $inspection->byteSize], JSON_THROW_ON_ERROR),
                    'occurred_at' => $now,
                ]);

                return new PkpMutationResult(
                    $pkpUuid,
                    $nextVersion,
                    $pkp->official_revision_uuid,
                    $pkp->pending_revision_uuid,
                    null,
                    $documentUuid,
                    $kind === 'signed_final' ? 'awaiting_inspection' : 'incomplete',
                    $pkp->lateness
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk(self::DISK)->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function download(User $actor, string $pkpUuid, string $documentUuid): StreamedResponse
    {
        $pkp = Pkp::query()->findOrFail($pkpUuid);
        $document = DB::table('pkp_documents')->where('uuid', $documentUuid)->first();
        if ($document === null) {
            abort(404, 'document_not_found');
        }

        $revision = DB::table('pkp_revisions')
            ->where('uuid', $document->revision_uuid)
            ->where('pkp_uuid', $pkp->uuid)
            ->first();
        if ($revision === null) {
            abort(404, 'document_not_found');
        }

        $owner = Pegawai::query()->findOrFail($pkp->pegawai_uuid);
        $submissionUuid = $revision->state === 'approved'
            ? $revision->approved_submission_uuid
            : $revision->current_submission_uuid;
        $submission = $submissionUuid === null ? null : DB::table('pkp_submissions')
            ->where('uuid', $submissionUuid)
            ->where('revision_uuid', $revision->uuid)
            ->first();
        $this->authorization->authorize(
            $actor,
            'download',
            new PkpContext($pkp, $revision, candidate: $document, submission: $submission, targetPegawai: $owner)
        );

        if (! Storage::disk(self::DISK)->exists($document->storage_path)) {
            abort(404, 'document_file_not_found');
        }

        return Storage::disk(self::DISK)->download($document->storage_path, $document->original_name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function decide(
        User $actor,
        string $pkpUuid,
        string $documentUuid,
        string $revisionUuid,
        int $expectedVersion,
        string $decision,
        ?string $reason = null,
        ?bool $matchesApprovedContent = null,
    ): PkpMutationResult {
        return DB::transaction(function () use ($actor, $pkpUuid, $documentUuid, $revisionUuid, $expectedVersion, $decision, $reason, $matchesApprovedContent): PkpMutationResult {
            $pkp = Pkp::query()->lockForUpdate()->findOrFail($pkpUuid);
            $revision = DB::table('pkp_revisions')
                ->where('uuid', $revisionUuid)
                ->where('pkp_uuid', $pkp->uuid)
                ->lockForUpdate()
                ->first();
            $document = DB::table('pkp_documents')
                ->where('uuid', $documentUuid)
                ->where('revision_uuid', $revisionUuid)
                ->lockForUpdate()
                ->first();

            if ($revision === null || $document === null) {
                abort(404, 'document_not_found');
            }

            $submission = $revision->approved_submission_uuid === null
                ? null
                : DB::table('pkp_submissions')->where('uuid', $revision->approved_submission_uuid)->first();
            $context = new PkpContext($pkp, $revision, candidate: $document, submission: $submission);
            $this->authorization->authorize($actor, 'documentDecision', $context);

            if ($expectedVersion !== $pkp->version) {
                abort(409, 'stale_version');
            }
            if ($pkp->official_revision_uuid !== $revisionUuid
                || $revision->pending_final_document_uuid !== $documentUuid
                || $document->kind !== 'signed_final'
            ) {
                abort(409, 'document_candidate_changed');
            }
            if (DB::table('pkp_document_reviews')->where('document_uuid', $documentUuid)->exists()) {
                abort(409, 'document_already_decided');
            }
            if ($decision === 'return' && ($reason === null || trim($reason) === '')) {
                throw ValidationException::withMessages(['reason' => 'A reason is required when returning a document.']);
            }
            if ($decision === 'accept' && $matchesApprovedContent !== true) {
                throw ValidationException::withMessages(['matches_approved_content' => ['content_not_confirmed']]);
            }

            $now = now('UTC');
            $nextVersion = $expectedVersion + 1;
            DB::table('pkp_document_reviews')->insert([
                'uuid' => (string) Str::uuid(),
                'document_uuid' => $documentUuid,
                'actor_user_uuid' => $actor->uuid,
                'decision' => $decision,
                'reason' => $decision === 'return' ? trim((string) $reason) : null,
                'basis' => 'post_approval_inspection',
                'approved_submission_uuid' => $revision->approved_submission_uuid,
                'replacement_base_document_uuid' => $document->replacement_of_document_uuid,
                'matches_approved_content' => $decision === 'accept' ? true : null,
                'decided_at' => $now,
                'created_at' => $now,
            ]);

            $finalDocumentUuid = $revision->final_document_uuid;
            $pendingDocumentUuid = null;
            if ($decision === 'accept') {
                $finalDocumentUuid = $documentUuid;
                $pendingDocumentUuid = null;
            }

            DB::table('pkp_revisions')->where('uuid', $revisionUuid)->update([
                'final_document_uuid' => $finalDocumentUuid,
                'pending_final_document_uuid' => $pendingDocumentUuid,
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
                'event_type' => $decision === 'accept' ? 'signed_final_accepted' : 'signed_final_returned',
                'before_json' => json_encode(['final_document_uuid' => $revision->final_document_uuid, 'pending_final_document_uuid' => $documentUuid, 'version' => $expectedVersion], JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['final_document_uuid' => $finalDocumentUuid, 'pending_final_document_uuid' => $pendingDocumentUuid, 'version' => $nextVersion], JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
            ]);

            $completeness = $finalDocumentUuid === null ? 'incomplete' : 'complete';

            return new PkpMutationResult(
                $pkpUuid,
                $nextVersion,
                $pkp->official_revision_uuid,
                $pkp->pending_revision_uuid,
                $revision->approved_submission_uuid,
                $finalDocumentUuid ?? $documentUuid,
                $completeness,
                $pkp->lateness
            );
        });
    }
}
