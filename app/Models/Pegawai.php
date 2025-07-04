<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\UserTrackingTrait;
use Venturecraft\Revisionable\RevisionableTrait;

class Pegawai extends Model
{
    use HasFactory, SoftDeletes, HasUuids, UserTrackingTrait, RevisionableTrait;

    protected $guarded = [];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    protected $casts = [
        'uuid' => 'string',
    ];

    protected $table = 'pegawai';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'uuid',
        'nip',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'no_hp',
        'foto',
        'agama',
        'jenis_kelamin',
        'status_perkawinan',
        'status_pegawai',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'nip', 'nip');
    }
}
