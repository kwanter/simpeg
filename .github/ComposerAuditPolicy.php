<?php

namespace Simpeg\Ci;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class ComposerAuditPolicy
{
    public const OWNER = 'kwanter';

    public const REVIEW_BY = '2026-11-21';

    private const REVIEWED_LARAVEL_ADVISORIES = [
        'GHSA-f9x3-7vq9-cph6' => 'Only the web session guard is configured; no TokenGuard path was identified.',
        'GHSA-jh5r-qr3c-85q8' => 'Debug-page exposure remains deployment-dependent; this is not release clearance.',
        'GHSA-crmm-hgp2-wgrp' => 'No local-filesystem temporary URL API is used in the traced flows.',
        'GHSA-5vg9-5847-vvmq' => 'SafeEmail rejects control characters in the reviewed mail entry points.',
    ];

    /** @return array<string, string> */
    public function check(string $json, int $status, DateTimeImmutable $today): array
    {
        try {
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Composer audit did not return valid JSON.', 0, $exception);
        }

        if (
            ! is_array($report)
            || ! isset($report['advisories'], $report['abandoned'])
            || ! is_array($report['advisories'])
            || ! is_array($report['abandoned'])
            || array_key_exists('error', $report)
            || array_key_exists('errors', $report)
            || ! empty($report['filter'])
            || ! empty($report['ignored-advisories'])
            || ! empty($report['abandoned'])
        ) {
            throw new RuntimeException('Composer audit is incomplete, filtered, or reports abandoned packages.');
        }

        $accepted = [];
        $records = 0;

        foreach ($report['advisories'] as $package => $advisories) {
            if (! is_string($package) || ! is_array($advisories) || ! array_is_list($advisories) || $advisories === []) {
                throw new RuntimeException('Composer audit contains an invalid advisory group.');
            }

            foreach ($advisories as $advisory) {
                if (! is_array($advisory) || ($advisory['packageName'] ?? null) !== $package) {
                    throw new RuntimeException('Composer audit contains an invalid package identity.');
                }

                $id = $this->githubAdvisoryId($advisory);

                if ($package !== 'laravel/framework' || ! isset(self::REVIEWED_LARAVEL_ADVISORIES[$id])) {
                    throw new RuntimeException("Unreviewed Composer advisory: {$package} {$id}.");
                }

                $accepted[$id] = self::REVIEWED_LARAVEL_ADVISORIES[$id];
                $records++;
            }
        }

        if ($status !== ($records === 0 ? 0 : 1)) {
            throw new RuntimeException("Composer audit exit status {$status} does not match its report.");
        }

        if ($accepted !== [] && $today->format('Y-m-d') > self::REVIEW_BY) {
            throw new RuntimeException('The Laravel advisory baseline has expired; owner: '.self::OWNER.'.');
        }

        return $accepted;
    }

    private function githubAdvisoryId(array $advisory): string
    {
        $ids = [];
        $link = $advisory['link'] ?? null;

        if (
            is_string($link)
            && preg_match('~^https://github\.com/(?:advisories|laravel/framework/security/advisories)/(GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4})$~', $link, $matches) === 1
        ) {
            $ids[] = $matches[1];
        }

        if (! isset($advisory['sources']) || ! is_array($advisory['sources']) || ! array_is_list($advisory['sources'])) {
            throw new RuntimeException('Composer audit contains invalid advisory sources.');
        }

        foreach ($advisory['sources'] as $source) {
            if (! is_array($source) || ! is_string($source['remoteId'] ?? null)) {
                throw new RuntimeException('Composer audit contains an invalid advisory source.');
            }

            if (preg_match('/^GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}$/', $source['remoteId']) === 1) {
                $ids[] = $source['remoteId'];
            }
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) !== 1) {
            throw new RuntimeException('Composer audit advisory identity is missing or ambiguous.');
        }

        return $ids[0];
    }
}
