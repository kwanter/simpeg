<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';

    protected $fillable = [
        'uuid',
        'tanggal',
        'nama',
        'jenis', // 'Libur Nasional' or 'Cuti Bersama'
        'keterangan'
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}