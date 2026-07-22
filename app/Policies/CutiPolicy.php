<?php

namespace App\Policies;

use App\Models\Cuti;
use App\Models\Pegawai;
use App\Models\User;

class CutiPolicy
{
    private function pegawaiUuidFor(User $user): ?string
    {
        return Pegawai::where('nip', $user->nip)->value('uuid');
    }

    private function isOwner(User $user, Cuti $cuti): bool
    {
        return $user->nip === $cuti->pegawai?->nip;
    }

    private function isAssignedPimpinan(User $user, Cuti $cuti): bool
    {
        return $user->hasRole('pimpinan') && $this->pegawaiUuidFor($user) === $cuti->pimpinan_uuid;
    }

    private function isAssignedAtasanPimpinan(User $user, Cuti $cuti): bool
    {
        return $user->hasRole('atasan-pimpinan') && $this->pegawaiUuidFor($user) === $cuti->atasan_pimpinan_uuid;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cuti $cuti): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'verifikator']) ||
            $this->isOwner($user, $cuti) ||
            $this->isAssignedPimpinan($user, $cuti) ||
            $this->isAssignedAtasanPimpinan($user, $cuti);
    }

    /**
     * Determine whether the user can view the name of the employee.
     */
    public function viewNamaPegawai(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'pimpinan', 'verifikator']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create cuti');
    }

    /**
     * Determine whether the user can update the model.
     * Owner may edit own pending cuti; staff roles may edit any pending cuti.
     */
    public function update(User $user, Cuti $cuti): bool
    {
        if (! $user->can('update cuti') || $cuti->status !== 'Pending') {
            return false;
        }

        return $this->isOwner($user, $cuti)
            || $user->hasAnyRole(['super-admin', 'admin', 'verifikator']);
    }

    /**
     * Determine whether the user can delete the model.
     * Owner may delete own pending cuti; staff roles may delete any pending cuti.
     */
    public function delete(User $user, Cuti $cuti): bool
    {
        if (! $user->can('delete cuti') || $cuti->status !== 'Pending') {
            return false;
        }

        return $this->isOwner($user, $cuti)
            || $user->hasAnyRole(['super-admin', 'admin', 'verifikator']);
    }

    /**
     * Determine whether the user can verify the model.
     */
    public function verify(User $user, Cuti $cuti): bool
    {
        return $user->can('verifikasi cuti') && $cuti->status == 'Pending';
    }

    /**
     * Determine whether the user can verify as pimpinan.
     */
    public function verifyPimpinan(User $user, Cuti $cuti): bool
    {
        return ($user->hasAnyRole(['super-admin', 'admin']) || $this->isAssignedPimpinan($user, $cuti)) &&
            $user->can('pimpinan cuti') &&
            $cuti->status == 'Disetujui Verifikator';
    }

    /**
     * Determine whether the user can verify as atasan pimpinan.
     */
    public function verifyAtasanPimpinan(User $user, Cuti $cuti): bool
    {
        return ($user->hasAnyRole(['super-admin', 'admin']) || $this->isAssignedAtasanPimpinan($user, $cuti)) &&
            $user->can('atasan pimpinan cuti') &&
            $cuti->status == 'Disetujui Pimpinan';
    }

    /**
     * Determine whether the user can edit the surat number.
     */
    public function editNoSurat(User $user, Cuti $cuti): bool
    {
        return in_array($cuti->status, ['Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan']) &&
            $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can print the PDF.
     */
    public function cetak(User $user, Cuti $cuti): bool
    {
        return in_array($cuti->status, ['Disetujui Pimpinan', 'Disetujui Atasan Pimpinan']) &&
            ! empty($cuti->no_surat_cuti) &&
            $this->view($user, $cuti);
    }

    /**
     * Determine whether the user can update all balances.
     */
    public function updateAllBalances(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cuti $cuti): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cuti $cuti): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }
}
