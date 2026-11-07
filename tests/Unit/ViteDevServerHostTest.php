<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Pins the Vite dev-server bind address recorded in
 * docs/review/dependency-review-monthly-pkp-2026-11-06.md.
 *
 * laravel-vite-plugin widens the host to 0.0.0.0 whenever LARAVEL_SAIL is set
 * and the config states no host, which exposes the dev server and its
 * /__open-in-editor endpoint to the whole LAN. These assertions read the host
 * Vite actually resolves, so the guard follows configuration behavior rather
 * than the text of vite.config.js.
 */
final class ViteDevServerHostTest extends TestCase
{
    /**
     * Addresses that keep the dev server unreachable from the network.
     */
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    public function test_sail_environment_still_binds_the_dev_server_to_loopback(): void
    {
        $resolved = $this->resolveServerHost(['LARAVEL_SAIL' => '1']);

        self::assertContains(
            $resolved,
            self::LOOPBACK_HOSTS,
            'Running Vite with LARAVEL_SAIL must not expose the dev server to the LAN.',
        );
    }

    public function test_default_environment_binds_the_dev_server_to_loopback(): void
    {
        // Vite's own default is a null host, which binds loopback only.
        $resolved = $this->resolveServerHost([]);

        self::assertTrue(
            $resolved === null || in_array($resolved, self::LOOPBACK_HOSTS, true),
            'The default dev-server host must stay loopback.',
        );
    }

    public function test_lan_binding_remains_an_explicit_opt_in(): void
    {
        $resolved = $this->resolveServerHost(
            ['LARAVEL_SAIL' => '1'],
            ['server' => ['host' => '0.0.0.0']],
        );

        self::assertSame(
            '0.0.0.0',
            $resolved,
            'Exposing the dev server on all interfaces must require an explicit override.',
        );
    }

    /**
     * Resolve the dev-server host exactly as `npm run dev` would.
     *
     * @param  array<string, string>  $env  extra environment for the probe
     * @param  array<string, mixed>  $inlineConfig  options a CLI flag would supply
     */
    private function resolveServerHost(array $env, array $inlineConfig = []): ?string
    {
        $script = <<<'JS'
        import { resolveConfig } from 'vite';

        const inline = JSON.parse(process.env.VITE_HOST_PROBE_INLINE ?? '{}');
        const resolved = await resolveConfig(
            { configFile: 'vite.config.js', mode: 'development', ...inline },
            'serve',
        );
        const host = resolved.server.host ?? null;
        process.stdout.write(JSON.stringify({ host }));
        JS;

        $process = new Process(
            [self::nodeBinary(), '--input-type=module', '-e', $script],
            dirname(__DIR__, 2),
            [
                // The probe resolves configuration only; it never starts a server.
                'LARAVEL_BYPASS_ENV_CHECK' => '1',
                'VITE_HOST_PROBE_INLINE' => json_encode((object) $inlineConfig),
            ] + $env,
        );
        $process->setTimeout(120);
        $process->run();

        self::assertTrue(
            $process->isSuccessful(),
            'Vite configuration could not be resolved: '.$process->getErrorOutput(),
        );

        /** @var array{host: string|null} $resolved */
        $resolved = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        return $resolved['host'];
    }

    private static function nodeBinary(): string
    {
        $candidates = array_filter([
            getenv('NODE_BINARY') ?: null,
            trim((string) shell_exec('command -v node 2>/dev/null')),
        ]);

        self::assertNotEmpty(
            $candidates,
            'Node is required to resolve the Vite dev-server configuration.',
        );

        return (string) reset($candidates);
    }
}
