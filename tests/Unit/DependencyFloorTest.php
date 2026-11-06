<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins the advisory patch floors recorded in
 * docs/review/dependency-review-monthly-pkp-2026-11-06.md.
 *
 * Assertions are floors, never exact versions, so routine lock refreshes stay
 * legal while a downgrade below a patched release fails loudly.
 */
final class DependencyFloorTest extends TestCase
{
    /**
     * Advisory patch floors for the locked PHP packages.
     */
    private const COMPOSER_FLOORS = [
        'symfony/http-foundation' => '6.4.50',
        'symfony/http-kernel' => '6.4.50',
        'symfony/routing' => '6.4.50',
        'symfony/mime' => '6.4.49',
        'league/flysystem' => '3.35.3',
        'symfony/yaml' => '7.4.23',
    ];

    /**
     * Advisory patch floors for the locked npm packages.
     */
    private const NPM_FLOORS = [
        'axios' => '1.20.0',
        'vite' => '8.1.6',
        'sucrase' => '3.35.1',
        'postcss-selector-parser' => '6.1.5',
        'source-map-js' => '1.2.2',
        'follow-redirects' => '1.16.2',
    ];

    /**
     * Packages whose vulnerable ranges must not reappear at any depth:
     * first element is the inclusive vulnerable floor, second the fix.
     */
    private const NPM_FORBIDDEN_RANGES = [
        'glob' => ['9.0.0', '14.0.2'],
        'minimatch' => ['7.0.0', '11.0.1'],
    ];

    public function test_php_dependencies_meet_advisory_patch_floors(): void
    {
        $lock = $this->composerLock();

        foreach (self::COMPOSER_FLOORS as $package => $floor) {
            $version = isset($lock[$package]) ? ltrim($lock[$package], 'v') : null;

            $this->assertNotNull(
                $version,
                "Package {$package} is missing from composer.lock."
            );
            $this->assertFalse(
                version_compare($version, $floor, '<'),
                "Package {$package} is locked at {$version}, below the patched floor {$floor}."
            );
        }
    }

    public function test_javascript_dependencies_meet_advisory_patch_floors(): void
    {
        $lock = $this->packageLock();

        foreach (self::NPM_FLOORS as $package => $floor) {
            $version = $this->installedNpmVersion($lock, $package);

            $this->assertNotNull(
                $version,
                "Package {$package} is missing from package-lock.json."
            );
            $this->assertFalse(
                version_compare($version, $floor, '<'),
                "Package {$package} is locked at {$version}, below the patched floor {$floor}."
            );
        }
    }

    public function test_no_copy_of_a_forbidden_npm_package_is_installed(): void
    {
        $lock = $this->packageLock();

        foreach (self::NPM_FORBIDDEN_RANGES as $package => [$vulnerableFloor, $fixed]) {
            foreach ($this->allNpmVersions($lock, $package) as $version) {
                $isVulnerable = version_compare($version, $vulnerableFloor, '>=')
                    && version_compare($version, $fixed, '<');

                $this->assertFalse(
                    $isVulnerable,
                    "Package {$package} is installed at {$version}, inside the vulnerable range."
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function composerLock(): array
    {
        $raw = file_get_contents($this->projectPath('composer.lock'));
        $this->assertIsString($raw, 'composer.lock must be readable.');

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $versions = [];

        foreach (array_merge($decoded['packages'] ?? [], $decoded['packages-dev'] ?? []) as $package) {
            $versions[$package['name']] = $package['version'];
        }

        return $versions;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageLock(): array
    {
        $raw = file_get_contents($this->projectPath('package-lock.json'));
        $this->assertIsString($raw, 'package-lock.json must be readable.');

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $lock
     */
    private function installedNpmVersion(array $lock, string $package): ?string
    {
        $entry = $lock['packages']['node_modules/'.$package] ?? null;

        return is_array($entry) && isset($entry['version']) ? $entry['version'] : null;
    }

    /**
     * @param  array<string, mixed>  $lock
     * @return list<string>
     */
    private function allNpmVersions(array $lock, string $package): array
    {
        $versions = [];
        $suffix = '/node_modules/'.$package;

        foreach ($lock['packages'] ?? [] as $path => $entry) {
            if (! is_array($entry) || ! isset($entry['version'])) {
                continue;
            }

            if ($path === 'node_modules/'.$package || str_ends_with((string) $path, $suffix)) {
                $versions[] = $entry['version'];
            }
        }

        return $versions;
    }

    private function projectPath(string $file): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$file;
    }
}
