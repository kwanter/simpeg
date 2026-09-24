<?php

namespace Tests\Unit;

use App\Http\Requests\StoreIzinRequest;
use App\Http\Requests\UpdateIzinRequest;
use App\Traits\JenisIzinRulesTrait;
use Tests\TestCase;

class IzinStructureTest extends TestCase
{
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
}
