<?php

namespace Tests\Unit\Policies;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\User;
use App\Policies\IzinPolicy;
use Tests\SimpegTestCase;

class IzinEditDecisionEquivalenceTest extends SimpegTestCase
{
    /**
     * The exact predicate IzinController::edit() computed before it was removed.
     * It is preserved here so that the removal can be proven rather than argued:
     * if the policy ever admits a request this predicate would have rejected,
     * deleting the branch would have changed behaviour.
     */
    private function removedAllowEdit(User $user, Izin $izin): bool
    {
        return $user->hasRole('super-admin') || $user->hasRole('admin') ||
            ($izin->verifikasi_atasan == 'Disetujui' && empty($izin->no_surat_izin)) ||
            ($izin->verifikasi_atasan == 'Belum Diverifikasi' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi');
    }

    public function test_policy_update_implies_the_removed_allow_edit_expression(): void
    {
        $policy = new IzinPolicy;

        $owner = $this->createUserWithRole('user');
        $ownerPegawai = Pegawai::where('nip', $owner->nip)->firstOrFail();
        $unrelated = $this->createUserWithRole('user');
        $superAdmin = $this->createUserWithRole('super-admin');
        $admin = $this->createUserWithRole('admin');
        $assignedAtasan = $this->createUserWithRole('atasan-pimpinan');
        $assignedPimpinan = $this->createUserWithRole('pimpinan');
        $assignedAtasanPegawai = Pegawai::where('nip', $assignedAtasan->nip)->firstOrFail();
        $assignedPimpinanPegawai = Pegawai::where('nip', $assignedPimpinan->nip)->firstOrFail();

        $izin = Izin::factory()->create([
            'pegawai_uuid' => $ownerPegawai->uuid,
            'atasan_pimpinan_uuid' => $assignedAtasanPegawai->uuid,
            'pimpinan_uuid' => $assignedPimpinanPegawai->uuid,
        ]);

        $actors = [
            'super-admin' => $superAdmin,
            'admin' => $admin,
            'owner' => $owner,
            'unrelated user' => $unrelated,
            'assigned atasan' => $assignedAtasan,
            'assigned pimpinan' => $assignedPimpinan,
        ];

        $checked = 0;
        $allowed = 0;

        foreach ($actors as $label => $user) {
            foreach (['Belum Diverifikasi', 'Disetujui'] as $verifikasiAtasan) {
                foreach (['Belum Diverifikasi', 'Disetujui'] as $verifikasiPimpinan) {
                    foreach ([null, 'IZIN-2026-001'] as $noSurat) {
                        $izin->verifikasi_atasan = $verifikasiAtasan;
                        $izin->verifikasi_pimpinan = $verifikasiPimpinan;
                        $izin->no_surat_izin = $noSurat;

                        $checked++;

                        if (! $policy->update($user, $izin)) {
                            continue;
                        }

                        $allowed++;

                        $this->assertTrue(
                            $this->removedAllowEdit($user, $izin),
                            sprintf(
                                'Counter-example: the policy permits update for %s with atasan=%s, pimpinan=%s, no_surat=%s, but the removed $allowEdit branch would have redirected with an error.',
                                $label,
                                $verifikasiAtasan,
                                $verifikasiPimpinan,
                                var_export($noSurat, true)
                            )
                        );
                    }
                }
            }
        }

        $this->assertSame(48, $checked, 'The matrix must enumerate every actor/status/letter combination.');

        $this->assertSame(
            18,
            $allowed,
            'Expected 18 permitted combinations: 8 for super-admin, 8 for admin, and 2 for an owner whose Izin is unverified. If this number moves, the equivalence must be re-proven before the removed branch is considered dead.'
        );
    }
}
