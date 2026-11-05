<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Pkp;
use App\Models\User;
use App\Support\PkpContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PkpPdfService
{
    public function __construct(private readonly PkpAuthorization $authorization) {}

    public function render(User $actor, string $pkpUuid, string $revisionUuid): Response|StreamedResponse
    {
        $pkp = Pkp::query()->findOrFail($pkpUuid);
        $revision = DB::table('pkp_revisions')
            ->where('uuid', $revisionUuid)
            ->where('pkp_uuid', $pkp->uuid)
            ->first();
        if ($revision === null) {
            abort(404, 'revision_not_found');
        }

        if ($revision->state !== 'approved'
            || $revision->approved_submission_uuid === null
        ) {
            abort(409, 'revision_not_approved');
        }

        $submission = DB::table('pkp_submissions')
            ->where('uuid', $revision->approved_submission_uuid)
            ->where('revision_uuid', $revisionUuid)
            ->where('state', 'approved')
            ->first();
        if ($submission === null) {
            abort(409, 'approved_submission_unavailable');
        }

        $owner = Pegawai::query()->findOrFail($pkp->pegawai_uuid);
        $this->authorization->authorize($actor, 'print', new PkpContext($pkp, $revision, submission: $submission, targetPegawai: $owner));

        if ($revision->source === 'external') {
            $sourceDocumentUuid = $submission->source_document_uuid;
            $sourceDocument = $sourceDocumentUuid === null
                ? null
                : DB::table('pkp_documents')
                    ->where('uuid', $sourceDocumentUuid)
                    ->where('revision_uuid', $revisionUuid)
                    ->where('kind', 'source')
                    ->first();
            if ($sourceDocument === null || ! Storage::disk('local')->exists($sourceDocument->storage_path)) {
                abort(404, 'approved_source_document_unavailable');
            }

            $storedHash = hash_file('sha256', Storage::disk('local')->path($sourceDocument->storage_path));
            if (! is_string($storedHash) || ! hash_equals($submission->source_sha256, $storedHash)) {
                abort(503, 'approved_source_integrity_failure');
            }

            return Storage::disk('local')->response($sourceDocument->storage_path, $sourceDocument->original_name, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ], 'inline');
        }

        $data = [
            'pkp' => $pkp,
            'revision' => $revision,
            'input' => json_decode($submission->input_snapshot, true, 512, JSON_THROW_ON_ERROR),
            'identity' => json_decode($submission->identity_snapshot, true, 512, JSON_THROW_ON_ERROR),
            'assessor' => json_decode($revision->approved_assessor_snapshot, true, 512, JSON_THROW_ON_ERROR),
            'score' => json_decode($submission->score_snapshot, true, 512, JSON_THROW_ON_ERROR),
            'submissionUuid' => $submission->uuid,
        ];

        $pdf = Pdf::loadView('pkp.pdf', $data)->setPaper('a4', 'landscape');
        $response = $pdf->stream('PKP-'.$pkp->year.'-'.$pkp->month.'-'.$pkp->uuid.'.pdf');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
