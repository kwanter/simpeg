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

    /**
     * Source of every method declared on the controller, keyed by method name.
     * Scoping to method bodies keeps these assertions off class-level noise such
     * as imports and the class docblock, and it names the offending method.
     *
     * @return array<string, string>
     */
    private function controllerMethodSources(): array
    {
        $sources = [];

        foreach ((new \ReflectionClass(IzinController::class))->getMethods() as $method) {
            $sources[$method->getName()] = $this->controllerMethodSource($method->getName());
        }

        return $sources;
    }

    public function test_no_controller_method_holds_perma_rule_logic(): void
    {
        foreach ($this->controllerMethodSources() as $method => $source) {
            foreach (['WorkdayService', 'isSingleLevel', 'maxWorkdays'] as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $source,
                    $method.'() must delegate '.$symbol.' to IzinSubmissionService (REQ-001..REQ-003).'
                );
            }
        }
    }

    public function test_no_controller_method_recomputes_the_edit_decision(): void
    {
        foreach ($this->controllerMethodSources() as $method => $source) {
            $this->assertStringNotContainsString(
                '$allowEdit',
                $source,
                $method.'() must not recompute an edit decision; IzinPolicy::update() is the single authority (REQ-004).'
            );
        }
    }

    public function test_exactly_one_controller_method_owns_the_missing_pegawai_guard(): void
    {
        $owners = [];

        foreach ($this->controllerMethodSources() as $method => $source) {
            if (str_contains($source, 'Data pegawai tidak ditemukan')) {
                $owners[] = $method;
            }
        }

        $this->assertCount(
            1,
            $owners,
            'Exactly one controller method must own the missing-pegawai guard; found in: '.implode(', ', $owners)
        );
    }

    public function test_destroy_retains_the_defensive_delete_guard(): void
    {
        $this->assertStringContainsString(
            'Pengajuan izin gagal dihapus.',
            $this->controllerMethodSource('destroy'),
            'The defensive delete guard is retained on purpose. Removing it would turn a future model-level veto into a silent success (AC-038).'
        );
    }

    public function test_generate_pdf_retains_the_filename_fallback(): void
    {
        $this->assertStringContainsString(
            '$izin->no_surat_izin ?? $izin->uuid',
            $this->controllerMethodSource('generatePdf'),
            'The unreachable PDF filename fallback is retained on purpose (AC-040).'
        );
    }
}
