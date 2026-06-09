<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\SimpegTestCase;

class RouteMiddlewareTest extends SimpegTestCase
{
    use RefreshDatabase;

    /*******************
     * Guest protection
     *******************/

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_cuti_index(): void
    {
        $response = $this->get(route('cuti.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_izin_index(): void
    {
        $response = $this->get(route('izin.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_pegawai_index(): void
    {
        $response = $this->get(route('pegawai.index'));
        $response->assertRedirect(route('login'));
    }

    /*******************
     * Super-admin access
     *******************/

    public function test_super_admin_can_access_permissions(): void
    {
        $user = $this->createUserWithRole('super-admin');
        $this->actingAs($user);

        $response = $this->get(route('permissions.index'));
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_roles(): void
    {
        $user = $this->createUserWithRole('super-admin');
        $this->actingAs($user);

        $response = $this->get(route('roles.index'));
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_users(): void
    {
        $user = $this->createUserWithRole('super-admin');
        $this->actingAs($user);

        $response = $this->get(route('users.index'));
        $response->assertStatus(200);
    }

    /*******************
     * Role-based denial
     *******************/

    public function test_regular_user_cannot_access_permissions(): void
    {
        $user = $this->createUserWithRole('user');
        $this->actingAs($user);

        $response = $this->get(route('permissions.index'));
        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_roles(): void
    {
        $user = $this->createUserWithRole('user');
        $this->actingAs($user);

        $response = $this->get(route('roles.index'));
        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_users(): void
    {
        $user = $this->createUserWithRole('user');
        $this->actingAs($user);

        $response = $this->get(route('users.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_users(): void
    {
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('view user');
        $this->actingAs($user);

        $response = $this->get(route('users.index'));
        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_permissions(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->actingAs($user);

        $response = $this->get(route('permissions.index'));
        $response->assertStatus(403);
    }

    /*******************
     * Route cache stability
     *******************/

    public function test_route_cache_does_not_throw(): void
    {
        $exitCode = Artisan::call('route:cache');
        $this->assertSame(0, $exitCode, 'route:cache should succeed without errors');

        // Clean up — clear cached routes so tests continue to work
        Artisan::call('route:clear');
    }

    public function test_no_generated_route_names(): void
    {
        $process = \Symfony\Component\Process\Process::fromShellCommandline('cd '.base_path().' && php artisan route:list --json');
        $process->run();
        $this->assertTrue($process->isSuccessful(), 'route:list --json must run successfully');
        $routes = json_decode($process->getOutput(), true);
        $this->assertIsArray($routes, 'route:list --json must return a JSON array');

        $generated = array_filter($routes, fn ($r) => str_starts_with($r['name'] ?? '', 'generated::'));
        $this->assertEmpty($generated, 'No routes should have generated:: names. Found: '.collect($generated)->pluck('name')->join(', '));
    }
}
