<?php

namespace Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simpeg\Ci\ComposerAuditPolicy;

require_once __DIR__.'/../../.github/ComposerAuditPolicy.php';

final class ComposerAuditPolicyTest extends TestCase
{
    public function test_accepts_only_the_reviewed_laravel_advisories_and_deduplicates_sources(): void
    {
        $advisories = [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
            $this->advisory('GHSA-jh5r-qr3c-85q8'),
            $this->advisory('GHSA-crmm-hgp2-wgrp'),
            $this->advisory('GHSA-5vg9-5847-vvmq'),
            $this->advisory('GHSA-5vg9-5847-vvmq', 'laravel/framework', false),
        ];

        $accepted = $this->check($this->report(['laravel/framework' => $advisories]), 1);

        $this->assertSame([
            'GHSA-f9x3-7vq9-cph6',
            'GHSA-jh5r-qr3c-85q8',
            'GHSA-crmm-hgp2-wgrp',
            'GHSA-5vg9-5847-vvmq',
        ], array_keys($accepted));
    }

    public function test_a_new_advisory_cannot_hide_behind_a_reviewed_one(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GHSA-aaaa-bbbb-cccc');

        $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
            $this->advisory('GHSA-aaaa-bbbb-cccc'),
        ]]), 1);
    }

    public function test_a_reviewed_id_does_not_exempt_another_package(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('other/package');

        $this->check($this->report(['other/package' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6', 'other/package'),
        ]]), 1);
    }

    public function test_conflicting_source_ids_are_not_treated_as_the_known_advisory(): void
    {
        $advisory = $this->advisory('GHSA-f9x3-7vq9-cph6');
        $advisory['sources'][] = ['name' => 'GitHub', 'remoteId' => 'GHSA-aaaa-bbbb-cccc'];

        $this->expectException(RuntimeException::class);
        $this->check($this->report(['laravel/framework' => [$advisory]]), 1);
    }

    /** @dataProvider invalidReports */
    public function test_invalid_or_filtered_reports_fail_closed(string $report, int $status): void
    {
        $this->expectException(RuntimeException::class);

        $this->check($report, $status);
    }

    public static function invalidReports(): array
    {
        return [
            'invalid JSON' => ['{', 1],
            'missing audit schema' => ['{}', 0],
            'registry failure' => ['{"error":"network unavailable"}', 1],
            'empty report with failure status' => ['{"advisories":[],"abandoned":[]}', 1],
            'abandoned package' => ['{"advisories":[],"abandoned":{"old/package":"new/package"}}', 2],
            'filtered report' => ['{"advisories":[],"abandoned":[],"filter":["severity"]}', 0],
            'ignored report' => ['{"advisories":[],"abandoned":[],"ignored-advisories":{"laravel/framework":[]}}', 0],
            'invalid advisory bucket' => ['{"advisories":{"laravel/framework":null},"abandoned":[]}', 1],
            'missing GHSA' => ['{"advisories":{"laravel/framework":[{"packageName":"laravel/framework","link":"https://example.test"}]},"abandoned":[]}', 1],
            'unexpected status' => ['{"advisories":[],"abandoned":[]}', 127],
        ];
    }

    public function test_a_clean_report_passes_without_using_an_expired_exception(): void
    {
        $this->assertSame([], $this->check($this->report([]), 0, '2026-11-22'));
    }

    public function test_advisories_with_a_success_status_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
        ]]), 0);
    }

    public function test_the_exception_is_valid_through_its_review_date(): void
    {
        $accepted = $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
        ]]), 1, '2026-11-21');

        $this->assertArrayHasKey('GHSA-f9x3-7vq9-cph6', $accepted);
    }

    public function test_the_exception_expires_after_its_review_date(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');

        $this->check($this->report(['laravel/framework' => [
            $this->advisory('GHSA-f9x3-7vq9-cph6'),
        ]]), 1, '2026-11-22');
    }

    private function check(string $json, int $status, string $today = '2026-11-07'): array
    {
        return (new ComposerAuditPolicy)->check($json, $status, new DateTimeImmutable($today));
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
