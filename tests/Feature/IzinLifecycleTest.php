<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Support\IzinType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\SimpegTestCase;

class IzinLifecycleTest extends SimpegTestCase
{
    public function test_store_binds_izin_to_authenticated_pegawai_and_initializes_approval_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $other = $this->createUserWithRole('user');
        $otherPegawai = Pegawai::where('nip', $other->nip)->firstOrFail();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $response = $this->actingAs($user)->post(route('izin.store'), [
            'pegawai_uuid' => $otherPegawai->uuid,
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'jumlah_hari' => 99,
            'alasan' => 'Personal matter',
            'status' => 'Disetujui',
            'verifikasi_atasan' => 'Disetujui',
        ]);

        $response->assertRedirect(route('izin.index'));
        $this->assertDatabaseHas('izin', [
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'jumlah_hari' => 2,
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);
        $this->assertDatabaseMissing('izin', ['pegawai_uuid' => $otherPegawai->uuid]);

        Carbon::setTestNow();
    }

    public function test_update_recalculates_workdays_and_ignores_unvalidated_state_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05'));
        $user = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'jumlah_hari' => 1,
        ]);

        $response = $this->actingAs($user)->put(route('izin.update', $izin->uuid), [
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'atasan_pimpinan_uuid' => $atasan->uuid,
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_mulai' => '2026-01-05',
            'tanggal_selesai' => '2026-01-06',
            'jumlah_hari' => 99,
            'alasan' => 'Updated personal matter',
            'status' => 'Disetujui',
            'verifikasi_atasan' => 'Disetujui',
        ]);

        $response->assertRedirect(route('izin.index'));
        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'jumlah_hari' => 2,
            'alasan' => 'Updated personal matter',
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_no_surat_update_changes_only_the_document_number(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = Izin::factory()->create([
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'status' => 'Disetujui Atasan',
            'no_surat_izin' => null,
            'alasan' => 'Existing reason',
        ]);

        $response = $this->actingAs($admin)->put(route('izin.update', $izin->uuid), [
            'no_surat_izin' => 'IZIN-2026-001',
        ]);

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Nomor surat izin berhasil diperbarui');
        $this->assertDatabaseHas('izin', [
            'uuid' => $izin->uuid,
            'no_surat_izin' => 'IZIN-2026-001',
            'status' => 'Disetujui Atasan',
            'alasan' => 'Existing reason',
        ]);

        Carbon::setTestNow();
    }

    public function test_owner_can_open_edit_form_for_unverified_izin(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $atasan = $this->createUserWithRole('atasan-pimpinan');
        $pimpinan = $this->createUserWithRole('pimpinan');
        $atasanPegawai = Pegawai::where('nip', $atasan->nip)->firstOrFail();
        $pimpinanPegawai = Pegawai::where('nip', $pimpinan->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'atasan_pimpinan_uuid' => $atasanPegawai->uuid,
            'pimpinan_uuid' => $pimpinanPegawai->uuid,
            'jenis_izin' => IzinType::KEPERLUAN_PRIBADI,
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
        ]);

        $response = $this->actingAs($owner)->get(route('izin.edit', $izin->uuid));

        $response->assertOk()
            ->assertViewIs('izin.edit')
            ->assertSee('Edit Pengajuan Izin')
            ->assertSee('name="jenis_izin"', false)
            ->assertSee('Update Izin')
            ->assertSee(route('izin.update', $izin->uuid), false);
    }

    public function test_admin_gets_placeholder_no_surat_form_when_pimpinan_is_not_verified(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = Izin::factory()->create([
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'status' => 'Disetujui Atasan',
            'no_surat_izin' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('izin.edit', $izin->uuid));

        $response->assertOk()
            ->assertViewIs('izin.edit')
            ->assertSee('Tambah Nomor Surat Izin')
            ->assertSee('name="no_surat_izin"', false)
            ->assertSee('<!-- Full form for admins -->', false)
            ->assertDontSee('Simpan Nomor Surat')
            ->assertDontSee('name="jenis_izin"', false);
    }

    public function test_owner_is_forbidden_from_editing_izin_after_atasan_approval_even_without_surat(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'status' => 'Disetujui Atasan',
            'no_surat_izin' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('izin.edit', $izin->uuid))
            ->assertForbidden();
    }

    public function test_owner_delete_removes_izin_and_documents_from_both_disks(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => 'legacy-and-private.pdf',
        ]);
        Storage::disk('local')->put('dokumen/izin/legacy-and-private.pdf', 'private');
        Storage::disk('public')->put('dokumen/izin/legacy-and-private.pdf', 'legacy');

        $response = $this->actingAs($owner)->delete(route('izin.destroy', $izin->uuid));

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Pengajuan izin berhasil dihapus');
        $this->assertDatabaseMissing('izin', ['uuid' => $izin->uuid]);
        Storage::disk('local')->assertMissing('dokumen/izin/legacy-and-private.pdf');
        Storage::disk('public')->assertMissing('dokumen/izin/legacy-and-private.pdf');
    }

    public function test_non_owner_cannot_delete_izin_or_its_document(): void
    {
        Storage::fake('local');
        $owner = $this->createUserWithRole('user');
        $other = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'dokumen' => 'protected.pdf',
        ]);
        Storage::disk('local')->put('dokumen/izin/protected.pdf', 'contents');

        $this->actingAs($other)->delete(route('izin.destroy', $izin->uuid))->assertForbidden();

        $this->assertDatabaseHas('izin', ['uuid' => $izin->uuid]);
        Storage::disk('local')->assertExists('dokumen/izin/protected.pdf');
    }
}
