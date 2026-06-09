<?php

namespace App\Services;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class IzinQueryService
{
    /**
     * Build a role-scoped izin query for the given user.
     *
     * @param  list<string>|null  $jenis
     * @return Builder<Izin>
     */
    public function forUser(User $user, ?array $jenis = null): Builder
    {
        $query = Izin::with('pegawai');

        if ($jenis !== null) {
            if (count($jenis) === 1) {
                $query->where('jenis_izin', $jenis[0]);
            } else {
                $query->whereIn('jenis_izin', $jenis);
            }
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $query;
        }

        $pegawaiUuid = Pegawai::where('nip', $user->nip)->value('uuid');

        if (! $pegawaiUuid) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('atasan-pimpinan')) {
            return $query->where('atasan_pimpinan_uuid', $pegawaiUuid);
        }

        if ($user->hasRole('pimpinan')) {
            return $query->where('pimpinan_uuid', $pegawaiUuid);
        }

        return $query->where('pegawai_uuid', $pegawaiUuid);
    }
}
