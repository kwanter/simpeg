<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\SimpegTestCase;

class UserPegawaiRelationTest extends SimpegTestCase
{
    public function test_auth_user_pegawai_returns_linked_pegawai(): void
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::factory()->create([
            'nip' => $user->nip,
            'user_uuid' => $user->uuid,
        ]);

        $this->actingAs($user);

        $this->assertTrue(Auth::user()->pegawai->is($pegawai));
    }

    public function test_user_without_pegawai_has_null_relation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertNull(Auth::user()->pegawai);
    }

    public function test_renaming_pegawai_nip_keeps_link(): void
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::factory()->create([
            'nip' => $user->nip,
            'user_uuid' => $user->uuid,
        ]);

        $pegawai->update(['nip' => '999999999999999999']);

        $this->actingAs($user);
        $this->assertTrue($user->pegawai()->first()->is($pegawai));
    }

    public function test_pegawai_user_inverse_returns_linked_user(): void
    {
        $user = User::factory()->create();
        $pegawai = Pegawai::factory()->create([
            'nip' => $user->nip,
            'user_uuid' => $user->uuid,
        ]);

        $this->assertTrue($pegawai->user->is($user));
    }

    public function test_pegawai_without_user_has_null_inverse(): void
    {
        $pegawai = Pegawai::factory()->create();

        $this->assertNull($pegawai->user);
    }
}
