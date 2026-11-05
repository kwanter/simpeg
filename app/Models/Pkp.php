<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Pkp extends Model
{
    use HasUuids;

    protected $table = 'pkp';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'year',
        'month',
        'version',
        'official_revision_uuid',
        'pending_revision_uuid',
        'first_submitted_at',
        'deadline_snapshot_at',
        'lateness',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'version' => 'integer',
        'first_submitted_at' => 'immutable_datetime',
        'deadline_snapshot_at' => 'immutable_datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }
}
