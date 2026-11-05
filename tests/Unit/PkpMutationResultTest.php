<?php

namespace Tests\Unit;

use App\Services\PkpMutationResult;
use App\Services\PkpSubmissionService;
use Tests\TestCase;

class PkpMutationResultTest extends TestCase
{
    public function test_result_type_autoloads_without_loading_submission_service(): void
    {
        $this->assertFalse(class_exists(PkpSubmissionService::class, false));
        $this->assertTrue(class_exists(PkpMutationResult::class));
    }

    public function test_result_fields_remain_immutable(): void
    {
        $result = new PkpMutationResult('pkp-uuid', 4, 'official-uuid', 'pending-uuid', 'submission-uuid');

        $this->assertSame('pkp-uuid', $result->recordUuid);
        $this->assertSame(4, $result->version);
        $this->assertSame('official-uuid', $result->officialRevisionUuid);
        $this->assertSame('pending-uuid', $result->pendingRevisionUuid);
        $this->assertSame('submission-uuid', $result->submissionUuid);
    }
}
