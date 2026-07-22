<?php

namespace Tests\Feature;

use App\Models\Cuti;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Storage;
use Tests\SimpegTestCase;

class CutiAuthorizationTest extends SimpegTestCase
{
    public function test_user_cannot_delete_another_users_pending_cuti(): void
    {
        $owner = $this->createUserWithRole('user');
        $owner->givePermissionTo(['delete cuti', 'update cuti', 'create cuti']);
        $attacker = $this->createUserWithRole('user');
        $attacker->givePermissionTo(['delete cuti', 'update cuti', 'create cuti']);

        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($attacker)->delete(route('cuti.destroy', $cuti->uuid));

        $response->assertForbidden();
        $this->assertDatabaseHas('cuti', ['uuid' => $cuti->uuid]);
    }

    public function test_owner_can_delete_own_pending_cuti(): void
    {
        $owner = $this->createUserWithRole('user');
        $owner->givePermissionTo(['delete cuti', 'update cuti', 'create cuti']);

        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($owner)->delete(route('cuti.destroy', $cuti->uuid));

        $response->assertRedirect(route('cuti.index'));
        $this->assertDatabaseMissing('cuti', ['uuid' => $cuti->uuid]);
    }

    public function test_pimpinan_permission_alone_does_not_expose_other_users_cuti(): void
    {
        $viewer = $this->createUserWithRole('user');
        $viewer->givePermissionTo('pimpinan cuti');
        $other = $this->createUserWithRole('user');
        $otherPegawai = Pegawai::where('nip', $other->nip)->firstOrFail();
        $otherCuti = Cuti::factory()->create(['pegawai_uuid' => $otherPegawai->uuid]);

        $response = $this->actingAs($viewer)->get(route('cuti.index'));

        $response->assertOk();
        $response->assertViewHas('cuti', fn ($cuti) => ! $cuti->getCollection()->contains('uuid', $otherCuti->uuid));
    }

    public function test_assigned_pimpinan_sees_only_assigned_cuti(): void
    {
        $pimpinan = $this->createUserWithRole('pimpinan');
        $pimpinanPegawai = Pegawai::where('nip', $pimpinan->nip)->firstOrFail();
        $owner = $this->createUserWithRole('user');
        $otherOwner = $this->createUserWithRole('user');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $otherPegawai = Pegawai::where('nip', $otherOwner->nip)->firstOrFail();
        $assigned = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'pimpinan_uuid' => $pimpinanPegawai->uuid,
        ]);
        $unassigned = Cuti::factory()->create(['pegawai_uuid' => $otherPegawai->uuid]);

        $response = $this->actingAs($pimpinan)->get(route('cuti.index'));

        $response->assertOk();
        $response->assertViewHas('cuti', function ($cuti) use ($assigned, $unassigned): bool {
            return $cuti->getCollection()->contains('uuid', $assigned->uuid)
                && ! $cuti->getCollection()->contains('uuid', $unassigned->uuid);
        });
    }

    public function test_guest_cannot_download_cuti_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/cuti/secret.pdf', 'pdf-bytes');

        $owner = $this->createUserWithRole('user');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'status' => 'Pending',
            'dokumen' => 'secret.pdf',
        ]);

        $this->get(route('cuti.dokumen', $cuti->uuid))->assertRedirect(route('login'));
    }

    public function test_other_user_cannot_download_cuti_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/cuti/secret.pdf', 'pdf-bytes');

        $owner = $this->createUserWithRole('user');
        $other = $this->createUserWithRole('user');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'status' => 'Pending',
            'dokumen' => 'secret.pdf',
        ]);

        $this->actingAs($other)->get(route('cuti.dokumen', $cuti->uuid))->assertForbidden();
    }

    public function test_owner_can_download_cuti_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('dokumen/cuti/secret.pdf', 'pdf-bytes');

        $owner = $this->createUserWithRole('user');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $cuti = Cuti::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'status' => 'Pending',
            'dokumen' => 'secret.pdf',
        ]);

        $this->actingAs($owner)->get(route('cuti.dokumen', $cuti->uuid))->assertOk();
    }
}
