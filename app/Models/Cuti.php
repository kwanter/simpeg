<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UserTrackingTrait;

class Cuti extends Model
{
    use HasFactory, UserTrackingTrait;

    protected $table = 'cuti';

    // Add this relationship method to the Cuti model
    public function atasanPimpinan()
    {
        return $this->belongsTo(Pegawai::class, 'atasan_pimpinan_uuid', 'uuid');
    }

    // Update the fillable array to include the new fields
    // Add these fields to your $fillable array
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
        'dokumen',
        'status',
        'verifikator_uuid',
        'status_verifikator',
        'catatan_verifikator',
        'tanggal_verifikasi',
        'pimpinan_uuid',
        'status_pimpinan',
        'catatan_pimpinan',
        'tanggal_verifikasi_pimpinan',
        'atasan_pimpinan_uuid',
        'status_atasan_pimpinan',
        'catatan_atasan_pimpinan',
        'tanggal_verifikasi_atasan_pimpinan',
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