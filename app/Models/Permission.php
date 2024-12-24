<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // Add this method to ensure UUID is set before saving
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
{
        return 'uuid';
    }

    public function getKey()
    {
        return $this->uuid;
    }

    public function toArray()
    {
        $array = parent::toArray();
        // Remove any circular references here if needed
        // For example:
        // unset($array['roles']);
        return $array;
    }

    protected $fillable = ['name', 'guard_name', 'uuid'];
}
