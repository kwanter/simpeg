<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PkpIdentityService
{
    /**
     * @return array{
     *     period_end: string,
     *     employee: array{pegawai_uuid: string, name: string, nip: string},
     *     rank: array{value: string, history_uuids: list<string>},
     *     position: array{value: string, history_uuids: list<string>},
     *     work_unit: array{value: string, history_uuids: list<string>}
     * }
     */
    public function resolve(Pegawai $pegawai, int $year, int $month): array
    {
        if ($year < 1900 || $year > 9999 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages(['period' => 'The assessed period is invalid.']);
        }

        $periodEnd = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Makassar')
            ->endOfMonth()
            ->toDateString();
        $rows = $this->effectiveHistoryRows($pegawai, $periodEnd);
        $rankRows = array_values(array_filter($rows, fn (\stdClass $row): bool => $row->kind === 'rank'));
        $positionRows = array_values(array_filter($rows, fn (\stdClass $row): bool => $row->kind === 'position'));
        $employeeName = $rows === [] ? $pegawai->nama : $rows[0]->employee_name;
        $employeeNip = $rows === [] ? $pegawai->nip : $rows[0]->employee_nip;
        $errors = [];

        if ($employeeName === null || trim($employeeName) === '') {
            $errors['identity.name'] = 'The employee name is required for the assessed period snapshot.';
        }
        if ($employeeNip === null || trim($employeeNip) === '') {
            $errors['identity.nip'] = 'The employee number is required for the assessed period snapshot.';
        }
        if ($rankRows === []) {
            $errors['identity.rank'] = 'No effective rank history exists at the end of the assessed month.';
        }
        if ($positionRows === []) {
            $errors['identity.position'] = 'No effective position history exists at the end of the assessed month.';
            $errors['identity.work_unit'] = 'No effective work-unit history exists at the end of the assessed month.';
        }

        $rankValues = array_values(array_unique(array_map(
            fn (\stdClass $row): string => trim((string) $row->value),
            $rankRows
        )));
        if ($rankRows !== [] && (in_array('', $rankValues, true) || count($rankValues) !== 1)) {
            $errors['identity.rank'] = 'The latest effective rank history is missing or conflicting.';
        }

        $positionValues = array_values(array_unique(array_map(
            fn (\stdClass $row): string => trim((string) $row->value),
            $positionRows
        )));
        if ($positionRows !== [] && (in_array('', $positionValues, true) || count($positionValues) !== 1)) {
            $errors['identity.position'] = 'The latest effective position history is missing or conflicting.';
        }

        $workUnitValues = array_values(array_unique(array_map(
            fn (\stdClass $row): string => trim((string) $row->work_unit),
            $positionRows
        )));
        if ($positionRows !== [] && (in_array('', $workUnitValues, true) || count($workUnitValues) !== 1)) {
            $errors['identity.work_unit'] = 'The latest effective work-unit history is missing or conflicting.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'period_end' => $periodEnd,
            'employee' => [
                'pegawai_uuid' => $pegawai->uuid,
                'name' => $employeeName,
                'nip' => $employeeNip,
            ],
            'rank' => [
                'value' => $rankValues[0],
                'history_uuids' => array_map(fn (\stdClass $row): string => $row->uuid, $rankRows),
            ],
            'position' => [
                'value' => $positionValues[0],
                'history_uuids' => array_map(fn (\stdClass $row): string => $row->uuid, $positionRows),
            ],
            'work_unit' => [
                'value' => $workUnitValues[0],
                'history_uuids' => array_map(fn (\stdClass $row): string => $row->uuid, $positionRows),
            ],
        ];
    }

    /** @return list<\stdClass> */
    private function effectiveHistoryRows(Pegawai $pegawai, string $periodEnd): array
    {
        $latestRank = RiwayatPangkat::query()->selectRaw('MAX(tmt)')
            ->where('pegawai_uuid', $pegawai->uuid)->whereDate('tmt', '<=', $periodEnd);
        $latestPosition = RiwayatJabatan::query()->selectRaw('MAX(tanggal_mulai)')
            ->where('pegawai_uuid', $pegawai->uuid)->whereDate('tanggal_mulai', '<=', $periodEnd);
        $ranks = RiwayatPangkat::query()
            ->join('pegawai as identity_owner', 'identity_owner.uuid', '=', 'riwayat_pangkat.pegawai_uuid')
            ->whereNull('identity_owner.deleted_at')
            ->where('pegawai_uuid', $pegawai->uuid)->where('tmt', $latestRank)
            ->selectRaw("riwayat_pangkat.uuid, 'rank' AS kind, pangkat_golongan AS value, NULL AS work_unit, identity_owner.nama AS employee_name, identity_owner.nip AS employee_nip")
            ->toBase();
        $positions = RiwayatJabatan::query()
            ->join('pegawai as identity_owner', 'identity_owner.uuid', '=', 'riwayat_jabatan.pegawai_uuid')
            ->whereNull('identity_owner.deleted_at')
            ->leftJoin('jabatan', 'jabatan.uuid', '=', 'riwayat_jabatan.jabatan_uuid')
            ->where('pegawai_uuid', $pegawai->uuid)->where('tanggal_mulai', $latestPosition)
            ->selectRaw("riwayat_jabatan.uuid, 'position' AS kind, jabatan.nama AS value, riwayat_jabatan.satuan_kerja AS work_unit, identity_owner.nama AS employee_name, identity_owner.nip AS employee_nip")
            ->toBase();

        // Employee identity, both histories, and their latest dates share one statement snapshot.
        return $ranks->unionAll($positions)->orderBy('uuid')->get()->all();
    }
}
