<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Venturecraft\Revisionable\RevisionableTrait;

class Izin extends Model
{
    use HasFactory, RevisionableTrait, \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'izin';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'no_surat_izin',
        'atasan_pimpinan_uuid',
        'pimpinan_uuid',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_mulai',
        'jam_selesai',
        'jumlah_hari',
        'alasan',
        'status',
        'keterangan',
        'dokumen',
        'verifikasi_atasan',
        'verifikasi_pimpinan',
        'tanggal_verifikasi_atasan',
        'tanggal_verifikasi_pimpinan',
        'catatan_atasan',
        'catatan_pimpinan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_verifikasi_atasan' => 'date',
        'tanggal_verifikasi_pimpinan' => 'date',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class , 'pegawai_uuid', 'uuid');
    }

    public function atasan_pimpinan()
    {
        return $this->belongsTo(Pegawai::class , 'atasan_pimpinan_uuid', 'uuid');
    }

    public function pimpinan()
    {
        return $this->belongsTo(Pegawai::class , 'pimpinan_uuid', 'uuid');
    }
}