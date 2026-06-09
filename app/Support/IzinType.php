<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Canonical source of truth for Izin jenis_izin taxonomy and PERMA No. 7/2016 rules.
 *
 * Single-level approval: Izin Keluar Kantor, Izin Pulang Cepat (Pasal 5).
 * Two-level approval: all other jenis, including Izin Tidak Masuk Kerja (Pasal 8).
 * Workday cap: Izin Tidak Masuk Kerja max 2 workdays.
 */
class IzinType
{
    public const SAKIT = 'Izin Sakit';

    public const KEPERLUAN_KELUARGA = 'Izin Keperluan Keluarga';

    public const KEPERLUAN_PRIBADI = 'Izin Keperluan Pribadi';

    public const DINAS_LUAR = 'Izin Dinas Luar';

    public const SETENGAH_HARI = 'Izin Setengah Hari';

    public const TERLAMBAT = 'Izin Terlambat';

    public const PULANG_CEPAT = 'Izin Pulang Cepat';

    public const KELUAR_KANTOR = 'Izin Keluar Kantor';

    public const TIDAK_MASUK = 'Izin Tidak Masuk Kerja';

    public const LAINNYA = 'Izin Lainnya';

    /**
     * Single-level (atasan-only) approval per Pasal 5 PERMA No. 7/2016.
     */
    private const SINGLE_LEVEL = [
        self::KELUAR_KANTOR,
        self::PULANG_CEPAT,
    ];

    private const PDF_TEMPLATES = [
        self::KELUAR_KANTOR => 'izin.pdf-keluar-kantor',
        self::PULANG_CEPAT => 'izin.pdf-keluar-kantor',
        self::TIDAK_MASUK => 'izin.pdf-tidak-masuk',
    ];

    private const MAX_WORKDAYS = [
        self::TIDAK_MASUK => 2,
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SAKIT,
            self::KEPERLUAN_KELUARGA,
            self::KEPERLUAN_PRIBADI,
            self::DINAS_LUAR,
            self::SETENGAH_HARI,
            self::TERLAMBAT,
            self::PULANG_CEPAT,
            self::KELUAR_KANTOR,
            self::TIDAK_MASUK,
            self::LAINNYA,
        ];
    }

    public static function isSingleLevel(string $jenis): bool
    {
        return in_array($jenis, self::SINGLE_LEVEL, true);
    }

    public static function isSameDay(string $jenis): bool
    {
        return self::isSingleLevel($jenis);
    }

    public static function maxWorkdays(string $jenis): ?int
    {
        return self::MAX_WORKDAYS[$jenis] ?? null;
    }

    public static function pdfTemplate(string $jenis): string
    {
        return self::PDF_TEMPLATES[$jenis] ?? 'izin.pdf';
    }

    /**
     * @return Collection<int, string>
     */
    public static function keluarKantorGroup(): Collection
    {
        return collect(self::SINGLE_LEVEL);
    }
}
