<?php

namespace App\Services;

use App\Models\User;
use App\Policies\PkpPolicy;
use App\Support\PkpContext;
use Illuminate\Auth\Access\AuthorizationException;

class PkpAuthorization
{
    public function __construct(private readonly PkpPolicy $policy) {}

    public function allows(User $actor, string $action, PkpContext $context): bool
    {
        return match ($action) {
            'viewOwnList' => $this->policy->viewOwnList($actor, $context),
            'create' => $this->policy->create($actor, $context),
            'manageAssessorSettings' => $this->policy->manageAssessorSettings($actor, $context),
            'manageDeadlines' => $this->policy->manageDeadlines($actor, $context),
            'reassignContent' => $this->policy->reassignContent($actor, $context),
            'reassignInspector' => $this->policy->reassignInspector($actor, $context),
            'viewOwnAssessorOptions' => $this->policy->viewOwnAssessorOptions($actor, $context),
            'viewAll' => $this->policy->viewAll($actor, $context),
            'save' => $this->policy->save($actor, $context),
            'submit' => $this->policy->submit($actor, $context),
            'revise' => $this->policy->revise($actor, $context),
            'refreshIdentity' => $this->policy->refreshIdentity($actor, $context),
            'contentDecision' => $this->policy->contentDecision($actor, $context),
            'documentUpload' => $this->policy->documentUpload($actor, $context),
            'download' => $this->policy->download($actor, $context),
            'documentDecision' => $this->policy->documentDecision($actor, $context),
            'viewAssignedAssessments' => $this->policy->viewAssignedAssessments($actor, $context),
            'view' => $this->policy->view($actor, $context),
            'print' => $this->policy->print($actor, $context),
            default => false,
        };
    }

    public function authorize(User $actor, string $action, PkpContext $context): void
    {
        if (! $this->allows($actor, $action, $context)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
