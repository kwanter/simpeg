<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    use HasFactory;

    protected $table = 'cuti';

    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'jenis_cuti',
        'tanggal_mulai',
        'tanggal_selesai',
        'lama_cuti',
        'alasan',
        'alamat_selama_cuti',
        'no_hp_selama_cuti',
        'status',
        'catatan_verifikator',
        'verifikator_uuid',
        'tanggal_verifikasi',
        'status_verifikator',
        'catatan_pimpinan',
        'pimpinan_uuid',
        'tanggal_verifikasi_pimpinan',
        'status_pimpinan',
        'dokumen'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }

    public function verifikator()
    {
        return $this->belongsTo(Pegawai::class, 'verifikator_uuid', 'uuid');
    }

    public function pimpinan()
    {
        return $this->belongsTo(Pegawai::class, 'pimpinan_uuid', 'uuid');
    }
}