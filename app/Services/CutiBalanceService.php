<?php

namespace App\Services;

use App\Models\CutiBalance;

class CutiBalanceService
{
    public function getOrCreateBalance(string $pegawaiUuid, int|string $year): CutiBalance
    {
        return CutiBalance::checkAndUpdateBalance($pegawaiUuid, (int) $year);
    }

    public function remainingDays(string $pegawaiUuid, int|string $year): int
    {
        $balance = CutiBalance::where('pegawai_uuid', $pegawaiUuid)
            ->where('year', (int) $year)
            ->first();

        if (! $balance) {
            $balance = $this->getOrCreateBalance($pegawaiUuid, $year);
        }

        return (int) $balance->remaining_days;
    }

    public function deductWorkdays(string $pegawaiUuid, int|string $year, int $workdays): CutiBalance
    {
        $balance = $this->getOrCreateBalance($pegawaiUuid, $year);
        $balance->used_days += $workdays;
        $balance->save();

        return $balance;
    }

    public function refundWorkdays(string $pegawaiUuid, int|string $year, int $workdays): CutiBalance
    {
        $balance = $this->getOrCreateBalance($pegawaiUuid, $year);
        $balance->used_days = max(0, $balance->used_days - $workdays);
        $balance->save();

        return $balance;
    }

    public function refreshBalance(string $pegawaiUuid, int|string $year): CutiBalance
    {
        return $this->getOrCreateBalance($pegawaiUuid, $year);
    }

    /**
     * @param  list<string>  $pegawaiUuids
     */
    public function refreshAll(array $pegawaiUuids, int|string $year): void
    {
        CutiBalance::bulkCheckAndUpdateBalance($pegawaiUuids, (int) $year);
    }
}
