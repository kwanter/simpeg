<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Venturecraft\Revisionable\RevisionableTrait;

class HariLibur extends Model
{
    use HasFactory, RevisionableTrait;

    protected $table = 'hari_libur';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where('nama', 'like', '%'.$search.'%');
        });
    }

    public static function getHariLibur($tanggal)
    {
        $hariLibur = HariLibur::where('tanggal', $tanggal)->first();
        if ($hariLibur) {
            return $hariLibur->nama;
        } else {
            return null;
        }
    }

    public static function getHariLiburByDateRange($startDate, $endDate)
    {
        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);
        $endDate->modify('+1 day');
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($startDate, $interval, $endDate);

        // Batch-fetch all holidays in range (fixes N+1 query)
        $dbHolidays = HariLibur::whereBetween('tanggal', [
            $startDate->format('Y-m-d'),
            (clone $endDate)->modify('-1 day')->format('Y-m-d'),
        ])->pluck('tanggal')->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : date('Y-m-d', strtotime($d)))->flip()->all();

        $holidays = [];
        foreach ($period as $date) {
            $day = $date->format('N');
            $dateStr = $date->format('Y-m-d');
            if ($day == 6 || $day == 7) {
                $holidays[] = $dateStr;
            } elseif (isset($dbHolidays[$dateStr])) {
                $holidays[] = $dateStr;
            }
        }

        return $holidays;
    }

    protected $fillable = [
        'uuid',
        'tanggal',
        'nama',
        'jenis', // 'Libur Nasional' or 'Cuti Bersama'
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
