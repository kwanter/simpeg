<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\UserTrackingTrait;

class RiwayatJabatan extends Model
{
    use HasFactory, SoftDeletes, HasUuids, UserTrackingTrait;

    protected $table = 'riwayat_jabatan';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'pegawai_nama',
        'satuan_kerja',
        'jabatan_uuid',
        'tanggal_mulai',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date:Y-m-d',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_uuid', 'uuid');
    }
}
