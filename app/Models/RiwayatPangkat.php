<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\UserTrackingTrait;
use Venturecraft\Revisionable\RevisionableTrait;

class RiwayatPangkat extends Model
{
    use HasFactory, SoftDeletes, HasUuids, UserTrackingTrait, RevisionableTrait;

    protected $guarded = [];

    protected $table = 'riwayat_pangkat';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'pangkat_golongan',
        'tmt',
        'nomor_sk',
        'tanggal_sk',
        'keterangan'
    ];

    protected $casts = [
        'tmt' => 'date:Y-m-d',
        'tanggal_sk' => 'date:Y-m-d',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }
}
