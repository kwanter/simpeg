<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class MiddlewareMatrixTest extends SimpegTestCase
{
    use RefreshDatabase;

    protected function grantPegawaiView($user): void
    {
        $user->givePermissionTo('view pegawai');
    }

    protected function grantJabatanView($user): void
    {
        $user->givePermissionTo('view jabatan');
    }

    /*******************
     * Hari Libur — role: super-admin|admin|atasan-pimpinan|pimpinan|verifikator
     *******************/

    public function test_guest_cannot_access_hari_libur_index(): void
    {
        $response = $this->get(route('hari-libur.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_hari_libur(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('hari-libur.index'));
        $response->assertStatus(403);
    }

    public function test_verifikator_can_access_hari_libur(): void
    {
        $user = $this->createUserWithRole('verifikator');
        $response = $this->actingAs($user)->get(route('hari-libur.index'));
        $response->assertStatus(200);
    }

    public function test_admin_can_access_hari_libur(): void
    {
        $user = $this->createUserWithRole('admin');
        $response = $this->actingAs($user)->get(route('hari-libur.index'));
        $response->assertStatus(200);
    }

    /*******************
     * Pegawai — role: super-admin|admin|atasan-pimpinan|pimpinan|verifikator
     * plus permission:view pegawai (controller-level)
     *******************/

    public function test_guest_cannot_access_pegawai_index(): void
    {
        $response = $this->get(route('pegawai.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_pegawai(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('pegawai.index'));
        $response->assertStatus(403);
    }

    public function test_verifikator_with_view_pegawai_can_access_pegawai(): void
    {
        $user = $this->createUserWithRole('verifikator');
        $this->grantPegawaiView($user);
        $response = $this->actingAs($user)->get(route('pegawai.index'));
        $response->assertStatus(200);
    }

    public function test_pimpinan_with_view_pegawai_can_access_pegawai(): void
    {
        $user = $this->createUserWithRole('pimpinan');
        $this->grantPegawaiView($user);
        $response = $this->actingAs($user)->get(route('pegawai.index'));
        $response->assertStatus(200);
    }

    public function test_atasan_pimpinan_with_view_pegawai_can_access_pegawai(): void
    {
        $user = $this->createUserWithRole('atasan-pimpinan');
        $this->grantPegawaiView($user);
        $response = $this->actingAs($user)->get(route('pegawai.index'));
        $response->assertStatus(200);
    }

    public function test_verifikator_without_view_pegawai_permission_is_denied(): void
    {
        $user = $this->createUserWithRole('verifikator');
        $response = $this->actingAs($user)->get(route('pegawai.index'));
        $response->assertStatus(403);
    }

    /*******************
     * Jabatan — same role group as pegawai
     * plus can:view jabatan (controller-level)
     *******************/

    public function test_guest_cannot_access_jabatan(): void
    {
        $response = $this->get(route('jabatan.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_jabatan(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('jabatan.index'));
        $response->assertStatus(403);
    }

    public function test_admin_with_view_jabatan_can_access(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->grantJabatanView($user);
        $response = $this->actingAs($user)->get(route('jabatan.index'));
        $response->assertStatus(200);
    }

    /*******************
     * Cuti — role: super-admin|admin|atasan-pimpinan|pimpinan|verifikator|user
     *******************/

    public function test_regular_user_can_access_cuti_index(): void
    {
        $user = $this->createUserWithRole('user');
        $user->givePermissionTo('create cuti');
        $response = $this->actingAs($user)->get(route('cuti.index'));
        $response->assertStatus(200);
    }

    /*******************
     * Izin — auth+verified only, policy handles the rest
     *******************/

    public function test_regular_user_can_access_izin_index(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('izin.index'));
        $response->assertStatus(200);
    }

    public function test_izin_keluar_kantor_route_is_reachable(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('izin.index-keluar-kantor'));
        $response->assertStatus(200);
    }

    public function test_izin_tidak_masuk_route_is_reachable(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('izin.index-tidak-masuk'));
        $response->assertStatus(200);
    }

    /*******************
     * Dashboard — auth+verified
     *******************/

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = $this->createUserWithRole('user');
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
    }
}
