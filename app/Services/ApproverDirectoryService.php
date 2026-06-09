<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class ApproverDirectoryService
{
    /**
     * @return Collection<int, object{pimpinan_uuid: string, nama: string}>
     */
    public function pimpinanList(): Collection
    {
        if (! Role::where('name', 'pimpinan')->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::role('pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as pimpinan_uuid', 'pegawai.nama')
            ->get();
    }

    /**
     * @return Collection<int, object{atasan_pimpinan_uuid: string, nama: string}>
     */
    public function atasanList(): Collection
    {
        if (! Role::where('name', 'atasan-pimpinan')->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::role('atasan-pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as atasan_pimpinan_uuid', 'pegawai.nama')
            ->get();
    }
}
