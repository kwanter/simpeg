<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Support\PkpContext;
use Illuminate\Support\Facades\DB;

class PkpPolicy
{
    public function viewOwnList(User $actor, PkpContext $context): bool
    {
        $pegawai = $actor->pegawai;

        return $this->isEligibleAccount($actor)
            && $pegawai !== null
            && ! $pegawai->trashed()
            && ($context->targetPegawai === null || $context->targetPegawai->uuid === $pegawai->uuid);
    }

    public function create(User $actor, PkpContext $context): bool
    {
        $owner = $context->targetPegawai;

        return $this->isEligibleAccount($actor)
            && $owner !== null
            && $owner->user_uuid === $actor->uuid
            && ! $owner->trashed();
    }

    public function viewOwnAssessorOptions(User $actor, PkpContext $context): bool
    {
        return $this->create($actor, $context);
    }

    public function viewAll(User $actor, PkpContext $context): bool
    {
        if (! $this->isEligibleAccount($actor) || ! $actor->hasAnyRole(['admin', 'super-admin'])) {
            return false;
        }

        $permission = Permission::query()
            ->where('name', 'pkp.view-all')
            ->where('guard_name', 'web')
            ->first();

        return $permission !== null && $actor->hasPermissionTo($permission);
    }

    public function save(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $owner = $context->targetPegawai;

        return $this->isEligibleAccount($actor)
            && $pkp !== null
            && $owner !== null
            && $owner->user_uuid === $actor->uuid
            && $pkp->pegawai_uuid === $owner->uuid
            && ! $owner->trashed();
    }

    public function submit(User $actor, PkpContext $context): bool
    {
        return $this->save($actor, $context);
    }

    public function revise(User $actor, PkpContext $context): bool
    {
        return $this->save($actor, $context);
    }

    public function refreshIdentity(User $actor, PkpContext $context): bool
    {
        return $this->save($actor, $context);
    }

    public function view(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $owner = $context->targetPegawai;

        if (! $this->isEligibleAccount($actor) || $pkp === null || $owner === null || $owner->trashed()) {
            return false;
        }

        $actorPegawai = $actor->pegawai;

        return $owner->user_uuid === $actor->uuid
            || $this->viewAll($actor, $context)
            || ($actorPegawai !== null
                && ! $actorPegawai->trashed()
                && $context->revision?->state === 'submitted'
                && $context->revision?->assessor_pegawai_uuid === $actorPegawai->uuid
                && $context->submission?->uuid === $context->revision?->current_submission_uuid
                && $context->submission?->state === 'submitted')
            || ($actorPegawai !== null
                && ! $actorPegawai->trashed()
                && $context->revision?->state === 'approved'
                && $context->revision?->inspector_pegawai_uuid === $actorPegawai->uuid
                && $context->submission?->uuid === $context->revision?->approved_submission_uuid
                && $context->submission?->state === 'approved')
            || ($actorPegawai !== null && ! $actorPegawai->trashed()
                && $context->submission !== null && $context->revision !== null
                && $context->submission->revision_uuid === $context->revision->uuid
                && in_array($context->submission->state, ['returned', 'approved'], true)
                && DB::table('pkp_decisions')->where('submission_uuid', $context->submission->uuid)->where('actor_user_uuid', $actor->uuid)->exists());
    }

    public function print(User $actor, PkpContext $context): bool
    {
        return $this->view($actor, $context)
            && $context->revision?->state === 'approved'
            && $context->revision?->approved_submission_uuid === $context->submission?->uuid
            && $context->submission?->state === 'approved';
    }

    public function viewAssignedAssessments(User $actor, PkpContext $context): bool
    {
        return $this->isEligibleAccount($actor)
            && $actor->pegawai !== null
            && ! $actor->pegawai->trashed();
    }

    public function contentDecision(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $revision = $context->revision;
        $submission = $context->submission;
        $assessor = $actor->pegawai;

        return $this->isEligibleAccount($actor)
            && $assessor !== null
            && ! $assessor->trashed()
            && $pkp !== null
            && $revision !== null
            && $submission !== null
            && ($pkp->pending_revision_uuid === $revision->uuid || $pkp->official_revision_uuid === $revision->uuid)
            && $revision->assessor_pegawai_uuid === $assessor->uuid
            && $revision->current_submission_uuid === $submission->uuid
            && $submission->revision_uuid === $revision->uuid;
    }

    public function documentUpload(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $revision = $context->revision;
        $owner = $context->targetPegawai;

        return $this->isEligibleAccount($actor)
            && $pkp !== null
            && $revision !== null
            && $owner !== null
            && $owner->user_uuid === $actor->uuid
            && $pkp->pegawai_uuid === $owner->uuid
            && ! $owner->trashed()
            && ($pkp->pending_revision_uuid === $revision->uuid || $pkp->official_revision_uuid === $revision->uuid);
    }

    public function download(User $actor, PkpContext $context): bool
    {
        $document = $context->candidate;
        $revision = $context->revision;

        if ($document === null || $revision === null || $document->revision_uuid !== $revision->uuid) {
            return false;
        }
        if ($this->save($actor, $context) || $this->viewAll($actor, $context)) {
            return $this->view($actor, $context);
        }
        $pegawai = $actor->pegawai;
        if (! $this->isEligibleAccount($actor) || $pegawai === null || $pegawai->trashed()
            || $context->targetPegawai === null || $context->targetPegawai->trashed()) {
            return false;
        }
        if ($revision->state === 'approved' && $revision->inspector_pegawai_uuid === $pegawai->uuid
            && $context->submission?->uuid === $revision->approved_submission_uuid) {
            return $this->view($actor, $context);
        }
        if ($revision->state === 'submitted' && $revision->assessor_pegawai_uuid === $pegawai->uuid
            && $document->kind === 'source' && $document->uuid === $context->submission?->source_document_uuid
            && $context->submission?->uuid === $revision->current_submission_uuid) {
            return $this->view($actor, $context);
        }

        return $document->kind === 'source' && DB::table('pkp_submissions as submission')
            ->join('pkp_decisions as decision', 'decision.submission_uuid', '=', 'submission.uuid')
            ->where('submission.revision_uuid', $revision->uuid)->where('submission.source_document_uuid', $document->uuid)
            ->where('decision.actor_user_uuid', $actor->uuid)->exists();
    }

    public function documentDecision(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $revision = $context->revision;
        $submission = $context->submission;
        $document = $context->candidate;
        $inspector = $actor->pegawai;

        return $this->isEligibleAccount($actor)
            && $inspector !== null
            && ! $inspector->trashed()
            && $pkp !== null
            && $revision !== null
            && $submission !== null
            && $document !== null
            && $pkp->official_revision_uuid === $revision->uuid
            && $revision->state === 'approved'
            && $revision->inspector_pegawai_uuid === $inspector->uuid
            && $revision->pending_final_document_uuid === $document->uuid
            && $revision->approved_submission_uuid === $submission->uuid
            && $submission->state === 'approved'
            && $document->revision_uuid === $revision->uuid
            && $document->kind === 'signed_final';
    }

    public function manageAssessorSettings(User $actor, PkpContext $context): bool
    {
        if (! $this->isEligibleAccount($actor) || ! $actor->hasAnyRole(['admin', 'super-admin'])) {
            return false;
        }

        $permission = Permission::query()
            ->where('name', 'pkp.manage-assessors')
            ->where('guard_name', 'web')
            ->first();

        return $permission !== null && $actor->hasPermissionTo($permission);
    }

    public function manageDeadlines(User $actor, PkpContext $context): bool
    {
        if (! $this->isEligibleAccount($actor) || ! $actor->hasAnyRole(['admin', 'super-admin'])) {
            return false;
        }

        $permission = Permission::query()
            ->where('name', 'pkp.manage-deadlines')
            ->where('guard_name', 'web')
            ->first();

        return $permission !== null && $actor->hasPermissionTo($permission);
    }

    public function reassignContent(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $revision = $context->revision;
        $target = $context->targetPegawai;
        $permission = Permission::query()
            ->where('name', 'pkp.reassign')
            ->where('guard_name', 'web')
            ->first();

        return $this->isEligibleAccount($actor)
            && $actor->hasAnyRole(['admin', 'super-admin'])
            && $permission !== null
            && $actor->hasPermissionTo($permission)
            && $pkp !== null
            && $revision !== null
            && $revision->pkp_uuid === $pkp->uuid
            && ($target === null || $target->uuid !== $pkp->pegawai_uuid);
    }

    public function reassignInspector(User $actor, PkpContext $context): bool
    {
        $pkp = $context->pkp;
        $revision = $context->revision;
        $target = $context->targetPegawai;
        $permission = Permission::query()
            ->where('name', 'pkp.reassign')
            ->where('guard_name', 'web')
            ->first();

        return $this->isEligibleAccount($actor)
            && $actor->hasAnyRole(['admin', 'super-admin'])
            && $permission !== null
            && $actor->hasPermissionTo($permission)
            && $pkp !== null
            && $revision !== null
            && $revision->pkp_uuid === $pkp->uuid
            && ($target === null || $target->uuid !== $pkp->pegawai_uuid);
    }

    private function isEligibleAccount(User $actor): bool
    {
        return $actor->status === '1'
            && $actor->email_verified_at !== null
            && ! $actor->trashed();
    }
}
