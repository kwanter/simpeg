<?php

namespace Tests\Feature;

use App\Models\CutiBalance;
use App\Models\Pegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class CutiBalanceRouteTest extends SimpegTestCase
{
    use RefreshDatabase;

    public function test_update_balance_redirects_with_success(): void
    {
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $year = now()->year;

        CutiBalance::create([
            'uuid' => fake()->uuid(),
            'pegawai_uuid' => $pegawai->uuid,
            'year' => $year,
            'total_days' => 12,
            'used_days' => 5,
            'carried_over' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('cuti.update-balance'));

        $response->assertRedirect(route('cuti.index'));
        $response->assertSessionHas('success');
    }

    public function test_update_balance_redirects_when_no_pegawai(): void
    {
        $user = $this->createUserWithRole('user');
        Pegawai::where('nip', $user->nip)->delete();

        $response = $this->actingAs($user)->get(route('cuti.update-balance'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_update_all_balances_requires_update_cuti_permission(): void
    {
        $user = $this->createUserWithRole('user');

        $response = $this->actingAs($user)->get(route('cuti.update-all-balances'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_update_all_balances_succeeds_with_permission(): void
    {
        $admin = $this->createUserWithRole('admin');
        $admin->givePermissionTo('update cuti');
        Pegawai::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('cuti.update-all-balances'));

        $response->assertRedirect(route('cuti.index'));
        $response->assertSessionHas('success');
    }

    public function test_balance_page_displays_for_authenticated_user(): void
    {
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        $response = $this->actingAs($user)->get(route('cuti.balance'));

        $response->assertOk();
        $response->assertViewIs('cuti.balance');
    }

    public function test_guest_cannot_access_balance_routes(): void
    {
        $this->get(route('cuti.balance'))->assertRedirect(route('login'));
        $this->get(route('cuti.update-balance'))->assertRedirect(route('login'));
        $this->get(route('cuti.update-all-balances'))->assertRedirect(route('login'));
    }
}
