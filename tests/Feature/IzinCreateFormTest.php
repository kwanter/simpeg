<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\IzinType;
use Illuminate\Support\Str;
use Tests\SimpegTestCase;

class IzinCreateFormTest extends SimpegTestCase
{
    private function createUserWithoutPegawai(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['uuid' => (string) Str::uuid()]
        );

        $user = User::factory()->create(['status' => '1']);
        $user->assignRole($role);

        return $user;
    }

    public function test_create_form_exposes_the_full_jenis_list_and_approver_lists(): void
    {
        $user = $this->createUserWithRole('user');

        $response = $this->actingAs($user)->get(route('izin.create'));

        $response->assertOk()->assertViewIs('izin.create');
        $response->assertViewHas('pegawai');
        $response->assertViewHas('jenisIzin', fn ($jenis) => $jenis === IzinType::all());
        $response->assertViewHas('pimpinanList');
        $response->assertViewHas('atasanList');
    }

    public function test_keluar_kantor_create_form_exposes_the_scalar_jenis_override(): void
    {
        $user = $this->createUserWithRole('user');

        $response = $this->actingAs($user)->get(route('izin.create-keluar-kantor'));

        $response->assertOk()->assertViewIs('izin.create-keluar-kantor');
        $response->assertViewHas('jenisIzin', fn ($jenis) => $jenis === IzinType::KELUAR_KANTOR);
        $response->assertViewHas('atasanList');
    }

    public function test_tidak_masuk_create_form_exposes_the_full_jenis_list(): void
    {
        $user = $this->createUserWithRole('user');

        $response = $this->actingAs($user)->get(route('izin.create-tidak-masuk'));

        $response->assertOk()->assertViewIs('izin.create-tidak-masuk');
        $response->assertViewHas('jenisIzin', fn ($jenis) => $jenis === IzinType::all());
        $response->assertViewHas('pimpinanList');
        $response->assertViewHas('atasanList');
    }

    public function test_create_forms_redirect_when_the_actor_has_no_linked_pegawai(): void
    {
        $user = $this->createUserWithoutPegawai();

        $this->actingAs($user)
            ->get(route('izin.create'))
            ->assertRedirect(route('izin.index'))
            ->assertSessionHas('error', 'Data pegawai tidak ditemukan');

        $this->actingAs($user)
            ->get(route('izin.create-keluar-kantor'))
            ->assertRedirect(route('izin.index'))
            ->assertSessionHas('error', 'Data pegawai tidak ditemukan');

        $this->actingAs($user)
            ->get(route('izin.create-tidak-masuk'))
            ->assertRedirect(route('izin.index'))
            ->assertSessionHas('error', 'Data pegawai tidak ditemukan');
    }
}
