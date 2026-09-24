<?php

namespace Tests\Feature;

use App\Http\Requests\UpdateIzinRequest;
use App\Models\Izin;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Tests\SimpegTestCase;

class IzinNoSuratBoundaryTest extends SimpegTestCase
{
    private function createAtasanApprovedIzin(): Izin
    {
        return Izin::factory()->create([
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Disetujui Atasan',
            'verifikasi_atasan' => 'Disetujui',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'no_surat_izin' => null,
            'alasan' => 'Existing reason',
        ]);
    }

    /**
     * Count only the selects that resolve an Izin by uuid. The count must stay
     * uuid-scoped: the no-surat path also queries the izin table for the
     * no_surat_izin uniqueness probe, and that query is not a resolution.
     */
    private function countIzinUuidLookups(callable $request): int
    {
        $lookups = 0;

        DB::listen(function ($query) use (&$lookups) {
            if (preg_match('/select .* from ["`]?izin["`]?\s+where\s+["`]?uuid["`]?/i', $query->sql)) {
                $lookups++;
            }
        });

        $request();

        return $lookups;
    }

    public function test_real_edit_form_payload_is_treated_as_a_no_surat_update(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();

        $response = $this->actingAs($admin)->post(route('izin.update', $izin->uuid), [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
        ]);

        $response->assertRedirect(route('izin.index'));
        $response->assertSessionHas('success', 'Nomor surat izin berhasil diperbarui');

        $persisted = $izin->fresh();
        $this->assertSame('IZIN-2026-001', $persisted->no_surat_izin);
        $this->assertSame('Disetujui Atasan', $persisted->status);
        $this->assertSame('Existing reason', $persisted->alasan);
    }

    public function test_payload_with_a_fourth_field_is_not_treated_as_a_no_surat_update(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();

        $response = $this->actingAs($admin)->post(route('izin.update', $izin->uuid), [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
            'catatan_pimpinan' => 'extra field',
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHasErrors(['jenis_izin', 'tanggal_mulai']);
        $this->assertNull($izin->fresh()->no_surat_izin);
    }

    public function test_unapproved_izin_is_not_treated_as_a_no_surat_update(): void
    {
        $owner = $this->createUserWithRole('user');
        $pegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $izin = Izin::factory()->create([
            'pegawai_uuid' => $pegawai->uuid,
            'jenis_izin' => 'Izin Keperluan Pribadi',
            'status' => 'Diajukan',
            'verifikasi_atasan' => 'Belum Diverifikasi',
            'verifikasi_pimpinan' => 'Belum Diverifikasi',
            'no_surat_izin' => null,
        ]);

        $response = $this->actingAs($owner)->put(route('izin.update', $izin->uuid), [
            'no_surat_izin' => 'IZIN-2026-002',
        ]);

        $response->assertSessionMissing('success');
        $response->assertSessionHasErrors(['jenis_izin', 'tanggal_mulai']);
        $this->assertNull($izin->fresh()->no_surat_izin);
    }

    public function test_full_update_resolves_the_izin_exactly_once(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();
        $atasan = Pegawai::factory()->create();
        $pimpinan = Pegawai::factory()->create();

        $lookups = $this->countIzinUuidLookups(function () use ($admin, $izin, $atasan, $pimpinan) {
            $this->actingAs($admin)->put(route('izin.update', $izin->uuid), [
                'jenis_izin' => 'Izin Keperluan Pribadi',
                'atasan_pimpinan_uuid' => $atasan->uuid,
                'pimpinan_uuid' => $pimpinan->uuid,
                'tanggal_mulai' => '2026-01-05',
                'tanggal_selesai' => '2026-01-06',
                'alasan' => 'Full update',
            ]);
        });

        $this->assertSame(
            1,
            $lookups,
            'A full update must resolve the Izin exactly once and reuse it (AC-021).'
        );
    }

    public function test_no_surat_update_resolves_the_izin_exactly_once(): void
    {
        $admin = $this->createUserWithRole('admin');
        $izin = $this->createAtasanApprovedIzin();

        $lookups = $this->countIzinUuidLookups(function () use ($admin, $izin) {
            $this->actingAs($admin)->post(route('izin.update', $izin->uuid), [
                '_token' => 'test-token',
                '_method' => 'PUT',
                'no_surat_izin' => 'IZIN-2026-001',
            ]);
        });

        $this->assertSame(
            1,
            $lookups,
            'The no-surat path must resolve the Izin exactly once and reuse it (AC-021).'
        );
    }

    public function test_is_no_surat_update_is_cached_for_the_request_lifetime(): void
    {
        $izin = $this->createAtasanApprovedIzin();

        $request = UpdateIzinRequest::create(route('izin.update', $izin->uuid), 'PUT', [
            '_token' => 'test-token',
            '_method' => 'PUT',
            'no_surat_izin' => 'IZIN-2026-001',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $this->assertTrue($request->isNoSuratUpdate());

        $request->merge(['extra_a' => '1', 'extra_b' => '2']);

        $this->assertTrue(
            $request->isNoSuratUpdate(),
            'A second evaluation must reuse the cached answer. Recomputing would flip the predicate, because the payload now exceeds three entries (REQ-005, spec §4.2).'
        );
    }
}
