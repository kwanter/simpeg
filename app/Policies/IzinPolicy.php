<?php

namespace App\Policies;

use App\Models\Izin;
use App\Models\User;

class IzinPolicy
{
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
    public function view(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'pimpinan', 'atasan-pimpinan']) || $user->nip === $izin->pegawai?->nip;
    }

    /**
     * Determine whether the user can view the name of the employee.
     */
    public function viewNamaPegawai(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin', 'pimpinan', 'atasan-pimpinan']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! $user->hasAnyRole(['pimpinan', 'atasan-pimpinan']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']) ||
            ($user->nip === $izin->pegawai?->nip &&
             $izin->verifikasi_atasan == 'Belum Diverifikasi' &&
             $izin->verifikasi_pimpinan == 'Belum Diverifikasi');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Izin $izin): bool
    {
        return $this->update($user, $izin);
    }

    /**
     * Determine whether the user can verify as atasan.
     */
    public function verifyAtasan(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['atasan-pimpinan', 'super-admin', 'admin']) &&
            $izin->verifikasi_atasan == 'Belum Diverifikasi';
    }

    /**
     * Determine whether the user can verify as pimpinan.
     */
    public function verifyPimpinan(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['pimpinan', 'super-admin', 'admin']) &&
            $izin->verifikasi_atasan == 'Disetujui' &&
            $izin->verifikasi_pimpinan == 'Belum Diverifikasi';
    }

    /**
     * Determine whether the user can print the PDF.
     */
    public function cetak(User $user, Izin $izin): bool
    {
        return in_array($izin->status, ['Disetujui', 'Disetujui Atasan']) && ! empty($izin->no_surat_izin);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Izin $izin): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin']);
    }
}
