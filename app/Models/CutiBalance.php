<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Venturecraft\Revisionable\RevisionableTrait;

class CutiBalance extends Model
{
    use HasFactory, RevisionableTrait;
    protected $table = 'cuti_balance';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $appends = ['remaining_days'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = Str::uuid()->toString();
        });
    }
    protected $fillable = [
        'uuid',
        'pegawai_uuid',
        'year',
        'total_days',
        'used_days',
        'carried_over'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_uuid', 'uuid');
    }

    // Calculate remaining days
    public function getRemainingDaysAttribute()
    {
        return $this->total_days + $this->carried_over - $this->used_days;
    }

    // Get formatted balance information
    public static function getFormattedBalance($pegawaiUuid)
    {
        $currentYear = date('Y');
        $balance = self::checkAndUpdateBalance($pegawaiUuid, $currentYear);
        return [
            'total_days' => $balance->total_days,
            'carried_over' => $balance->carried_over,
            'used_days' => $balance->used_days,
            'remaining_days' => $balance->remaining_days,
        ];
    }

    // Static method to check and update cuti balance for an employee
    public static function checkAndUpdateBalance($pegawaiUuid, $year)
    {
        $balance = self::where('pegawai_uuid', $pegawaiUuid)
            ->where('year', $year)
            ->first();

        if (!$balance) {
            // Create new balance for current year
            $carriedOver = 0;

            // Check if there's a previous year balance to carry over (max 6 days)
            $previousYearBalance = self::where('pegawai_uuid', $pegawaiUuid)
                ->where('year', $year - 1)
                ->first();

            if ($previousYearBalance) {
                $remainingPreviousYear = $previousYearBalance->total_days +
                                        $previousYearBalance->carried_over -
                                        $previousYearBalance->used_days;
                $carriedOver = min(6, max(0, $remainingPreviousYear));
            }

            $balance = self::create([
                'uuid' => \Illuminate\Support\Str::uuid(),
                'pegawai_uuid' => $pegawaiUuid,
                'year' => $year,
                'total_days' => 12, // Default annual leave
                'used_days' => 0,
                'carried_over' => $carriedOver
            ]);
        } else {
            // Update carried over days if there's a previous year balance
            $previousYearBalance = self::where('pegawai_uuid', $pegawaiUuid)
                ->where('year', $year - 1)
                ->first();

            if ($previousYearBalance) {
                $remainingPreviousYear = $previousYearBalance->total_days +
                                        $previousYearBalance->carried_over -
                                        $previousYearBalance->used_days;
                $carriedOver = min(6, max(0, $remainingPreviousYear));
                $balance->carried_over = $carriedOver;
                $balance->save();
            }
        }

        return $balance;
    }
}
