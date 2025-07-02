<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Venturecraft\Revisionable\RevisionableTrait;

class Pangkat extends Model
{
    use HasFactory, RevisionableTrait;

    protected $table = 'pangkat';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';
}
