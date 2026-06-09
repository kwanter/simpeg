<?php

namespace Tests\Unit\Services;

use App\Models\Pegawai;
use App\Services\ApproverDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SimpegTestCase;

class ApproverDirectoryServiceTest extends SimpegTestCase
{
    use RefreshDatabase;

    public function test_pimpinan_list_returns_pegawai_uuid_and_nama_for_pimpinan_role(): void
    {
        $pimUser = $this->createUserWithRole('pimpinan', [], ['nama' => 'Budi Pimpinan']);

        $this->createUserWithRole('user', [], ['nama' => 'Andi Biasa']);

        $list = (new ApproverDirectoryService)->pimpinanList();

        $this->assertCount(1, $list);
        $this->assertSame(Pegawai::where('nip', $pimUser->nip)->firstOrFail()->uuid, $list->first()->pimpinan_uuid);
        $this->assertSame('Budi Pimpinan', $list->first()->nama);
    }

    public function test_atasan_list_returns_pegawai_uuid_and_nama_for_atasan_role(): void
    {
        $atsUser = $this->createUserWithRole('atasan-pimpinan', [], ['nama' => 'Citra Atasan']);

        $list = (new ApproverDirectoryService)->atasanList();

        $this->assertCount(1, $list);
        $this->assertSame(Pegawai::where('nip', $atsUser->nip)->firstOrFail()->uuid, $list->first()->atasan_pimpinan_uuid);
        $this->assertSame('Citra Atasan', $list->first()->nama);
    }

    public function test_returns_empty_collection_when_no_users_have_role(): void
    {
        $this->assertCount(0, (new ApproverDirectoryService)->pimpinanList());
        $this->assertCount(0, (new ApproverDirectoryService)->atasanList());
    }
}
