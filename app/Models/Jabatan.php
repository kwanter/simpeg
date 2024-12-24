<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jabatan extends Model
{
    use HasFactory;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['uuid', 'nama', 'deskripsi', 'parent_uuid'];
    protected $table = 'jabatan';
    public function parent()
    {
        return $this->belongsTo(Jabatan::class, 'parent_uuid', 'uuid');
    }

    public function children()
    {
        return $this->hasMany(Jabatan::class, 'parent_uuid', 'uuid');
    }
}
