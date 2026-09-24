<?php

namespace Tests\Unit;

use App\Http\Controllers\IzinController;
use App\Http\Requests\StoreIzinRequest;
use App\Http\Requests\UpdateIzinRequest;
use App\Traits\JenisIzinRulesTrait;
use Tests\TestCase;

class IzinStructureTest extends TestCase
{
    private function controllerMethodSource(string $method): string
    {
        $reflection = new \ReflectionMethod(IzinController::class, $method);
        $lines = file($reflection->getFileName(), FILE_IGNORE_NEW_LINES);

        return implode("\n", array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    public function test_shared_perma_trait_exists_in_the_repository_trait_namespace(): void
    {
        $this->assertTrue(
            trait_exists(JenisIzinRulesTrait::class),
            'The shared PERMA rule trait must exist at App\Traits\JenisIzinRulesTrait (spec decision D-004).'
        );

        $reflection = new \ReflectionClass(JenisIzinRulesTrait::class);

        $this->assertSame('App\Traits', $reflection->getNamespaceName());
        $this->assertFileExists(app_path('Traits/JenisIzinRulesTrait.php'));
    }

    public function test_both_izin_requests_consume_the_shared_trait(): void
    {
        foreach ([StoreIzinRequest::class, UpdateIzinRequest::class] as $requestClass) {
            $this->assertContains(
                JenisIzinRulesTrait::class,
                (new \ReflectionClass($requestClass))->getTraitNames(),
                $requestClass.' must compose the shared PERMA rule trait.'
            );
        }
    }

    public function test_neither_izin_request_keeps_a_local_perma_rule_copy(): void
    {
        $traitFile = 'app/Traits/JenisIzinRulesTrait.php';

        foreach ([StoreIzinRequest::class, UpdateIzinRequest::class] as $requestClass) {
            foreach (['jenis', 'jenisRules', 'messages'] as $method) {
                $this->assertStringEndsWith(
                    $traitFile,
                    (new \ReflectionMethod($requestClass, $method))->getFileName(),
                    $requestClass.'::'.$method.'() must be inherited from the shared trait, not declared locally.'
                );
            }
        }
    }

    public function test_store_action_holds_no_perma_rule_logic(): void
    {
        $source = $this->controllerMethodSource('store');

        foreach (['WorkdayService', 'isSingleLevel', 'maxWorkdays'] as $symbol) {
            $this->assertStringNotContainsString(
                $symbol,
                $source,
                'IzinController::store() must delegate '.$symbol.' to IzinSubmissionService.'
            );
        }
    }

    public function test_update_action_holds_no_perma_rule_logic(): void
    {
        $source = $this->controllerMethodSource('update');

        foreach (['WorkdayService', 'isSingleLevel', 'maxWorkdays'] as $symbol) {
            $this->assertStringNotContainsString(
                $symbol,
                $source,
                'IzinController::update() must delegate '.$symbol.' to IzinSubmissionService.'
            );
        }
    }

    public function test_controller_holds_no_perma_rule_logic_at_all(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/IzinController.php'));

        foreach (['WorkdayService', 'isSingleLevel', 'maxWorkdays'] as $symbol) {
            $this->assertStringNotContainsString(
                $symbol,
                $source,
                'IzinSubmissionService must be the only implementation of '.$symbol.' (REQ-001..REQ-003).'
            );
        }
    }

    public function test_controller_holds_no_local_edit_authorization_decision(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/IzinController.php'));

        $this->assertStringNotContainsString(
            '$allowEdit',
            $source,
            'IzinController must not recompute an edit decision; IzinPolicy::update() is the single authority (REQ-004).'
        );
    }

    public function test_controller_holds_exactly_one_missing_pegawai_guard(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/IzinController.php'));

        $this->assertSame(
            1,
            substr_count($source, 'Data pegawai tidak ditemukan'),
            'The three create actions must share one missing-pegawai guard rather than repeating the message (REQ-011).'
        );
    }

    public function test_controller_retains_the_deliberately_kept_branches(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/IzinController.php'));

        $this->assertStringContainsString(
            'Pengajuan izin gagal dihapus.',
            $source,
            'The defensive delete guard is retained on purpose. Removing it would turn a future model-level veto into a silent success (AC-038).'
        );

        $this->assertStringContainsString(
            '$izin->no_surat_izin ?? $izin->uuid',
            $source,
            'The unreachable PDF filename fallback is retained on purpose (AC-040).'
        );
    }
}
