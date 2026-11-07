<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simpeg\Ci\ComposerAuditPolicy;

require_once __DIR__.'/../../.github/ComposerAuditPolicy.php';

final class ComposerAuditPolicyTest extends TestCase
{
    public function test_a_clean_report_passes(): void
    {
        $this->assertSame([], $this->check($this->report([]), 0));
    }

    public function test_any_advisory_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GHSA-f9x3-7vq9-cph6');

        $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
        ]]), 1);
    }

    public function test_an_advisory_on_another_package_also_fails_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('other/package');

        $this->check($this->report(['other/package' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6', 'other/package'),
        ]]), 1);
    }

    public function test_an_unambiguous_source_id_does_not_reopen_the_gate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GHSA-5vg9-5847-vvmq');

        $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-5vg9-5847-vvmq', 'laravel/framework', false),
        ]]), 1);
    }

    public function test_an_ambiguous_advisory_identity_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('identity is missing or ambiguous');

        $advisory = $this->advisory('GHSA-f9x3-7vq9-cph6');
        $advisory['sources'][] = ['name' => 'GitHub', 'remoteId' => 'GHSA-aaaa-bbbb-cccc'];

        $this->check($this->report(['laravel/framework' => [$advisory]]), 1);
    }

    #[DataProvider('invalidReports')]
    public function test_invalid_or_filtered_reports_fail_closed(string $json, int $status): void
    {
        $this->expectException(RuntimeException::class);

        $this->check($json, $status);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function invalidReports(): array
    {
        return [
            'invalid json' => ['not json', 0],
            'missing report keys' => ['{"advisories":{}}', 0],
            'registry failure' => ['{"error":"curl error 7"}', 0],
            'abandoned package' => ['{"advisories":{},"abandoned":{"some/package":"no replacement"},"filter":[]}', 0],
            'filtered report' => ['{"advisories":{},"abandoned":[],"filter":["laravel/framework"]}', 0],
            'ignored advisories' => ['{"advisories":{},"abandoned":[],"filter":[],"ignored-advisories":{"laravel/framework":[]}}', 0],
            'missing GHSA' => ['{"advisories":{"laravel/framework":[{"packageName":"laravel/framework","link":"https://example.test"}]},"abandoned":[]}', 1],
            'status mismatch' => ['{"advisories":{},"abandoned":[],"filter":[]}', 1],
            'unexpected process status' => ['{"advisories":{},"abandoned":[],"filter":[]}', 127],
        ];
    }

    private function check(string $json, int $status): array
    {
        return (new ComposerAuditPolicy)->check($json, $status);
    }

    private function report(array $advisories): string
    {
        return json_encode(['advisories' => $advisories, 'abandoned' => [], 'filter' => []], JSON_THROW_ON_ERROR);
    }

    private function advisory(string $id, string $package = 'laravel/framework', bool $githubSource = true): array
    {
        return [
            'advisoryId' => 'PKSA-test-1234-5678',
            'packageName' => $package,
            'affectedVersions' => '<12.68.0',
            'title' => 'Controlled advisory fixture',
            'cve' => null,
            'link' => 'https://github.com/'.($githubSource ? 'advisories/' : 'laravel/framework/security/advisories/').$id,
            'reportedAt' => '2026-11-06T00:00:00+00:00',
            'sources' => [[
                'name' => $githubSource ? 'GitHub' : 'FriendsOfPHP/security-advisories',
                'remoteId' => $githubSource ? $id : 'laravel/framework/CVE-2026-48019.yaml',
            ]],
            'severity' => $githubSource ? 'critical' : null,
        ];
    }
}
