<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Venturecraft\Revisionable\RevisionableTrait;

class Role extends SpatieRole
{
    use HasFactory,HasUuids, RevisionableTrait, SoftDeletes;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
        });
    }

    // Override the getKeyName method to return 'uuid'
    public function getKeyName()
    {
        return 'uuid';
    }

    // Override the getKey method to return the uuid
    public function getKey()
    {
        return $this->uuid;
    }

    protected $fillable = ['name', 'guard_name', 'uuid'];
}
