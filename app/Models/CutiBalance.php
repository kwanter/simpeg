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
        'carried_over',
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
        // Batch fetch current and previous year in one query to reduce DB roundtrips (N+1 optimization)
        $balances = self::where('pegawai_uuid', $pegawaiUuid)
            ->whereIn('year', [$year, $year - 1])
            ->get()
            ->keyBy('year');

        $balance = $balances->get($year);
        $previousYearBalance = $balances->get($year - 1);

        $carriedOver = 0;
        if ($previousYearBalance) {
            $remainingPreviousYear = $previousYearBalance->total_days +
                                    $previousYearBalance->carried_over -
                                    $previousYearBalance->used_days;
            $carriedOver = min(6, max(0, $remainingPreviousYear));
        }

        if (! $balance) {
            $balance = self::create([
                'uuid' => (string) Str::uuid(),
                'pegawai_uuid' => $pegawaiUuid,
                'year' => $year,
                'total_days' => 12, // Default annual leave
                'used_days' => 0,
                'carried_over' => $carriedOver,
            ]);
        } else {
            // Only save if carried_over has actually changed to save a query
            if ($balance->carried_over != $carriedOver) {
                $balance->carried_over = $carriedOver;
                $balance->save();
            }
        }

        return $balance;
    }

    /**
     * Bulk update cuti balances for multiple employees (Fixes N+1 in loops)
     */
    public static function bulkCheckAndUpdateBalance(array $pegawaiUuids, $year)
    {
        // Fetch all current and previous year balances for the given employees in one query
        $allBalances = self::whereIn('pegawai_uuid', $pegawaiUuids)
            ->whereIn('year', [$year, $year - 1])
            ->get()
            ->groupBy('pegawai_uuid');

        foreach ($pegawaiUuids as $pegawaiUuid) {
            $pegawaiBalances = $allBalances->get($pegawaiUuid, collect())->keyBy('year');
            $balance = $pegawaiBalances->get($year);
            $previousYearBalance = $pegawaiBalances->get($year - 1);

            $carriedOver = 0;
            if ($previousYearBalance) {
                $remainingPreviousYear = $previousYearBalance->total_days +
                                        $previousYearBalance->carried_over -
                                        $previousYearBalance->used_days;
                $carriedOver = min(6, max(0, $remainingPreviousYear));
            }

            if (! $balance) {
                self::create([
                    'uuid' => (string) Str::uuid(),
                    'pegawai_uuid' => $pegawaiUuid,
                    'year' => $year,
                    'total_days' => 12,
                    'used_days' => 0,
                    'carried_over' => $carriedOver,
                ]);
            } else {
                if ($balance->carried_over != $carriedOver) {
                    $balance->carried_over = $carriedOver;
                    $balance->save();
                }
            }
        }
    }
}
