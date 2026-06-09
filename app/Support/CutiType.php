<?php

namespace App\Support;

/**
 * Canonical source of truth for Cuti jenis_cuti taxonomy.
 *
 * Balance tracking: Cuti Tahunan only.
 * Eligibility checks: Cuti Tahunan (remaining days, mutual exclusion) and Cuti Besar (5yr service, 90-day cap, mutual exclusion).
 */
class CutiType
{
    public const TAHUNAN = 'Cuti Tahunan';

    public const SAKIT = 'Cuti Sakit';

    public const MELAHIRKAN = 'Cuti Melahirkan';

    public const ALASAN_PENTING = 'Cuti Alasan Penting';

    public const BESAR = 'Cuti Besar';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TAHUNAN,
            self::SAKIT,
            self::MELAHIRKAN,
            self::ALASAN_PENTING,
            self::BESAR,
        ];
    }

    /**
     * Whether this leave type tracks annual balance.
     */
    public static function requiresBalance(string $jenis): bool
    {
        return $jenis === self::TAHUNAN;
    }

    /**
     * Whether this leave type needs eligibility validation (annual check or Cuti Besar rules).
     */
    public static function requiresEligibilityCheck(string $jenis): bool
    {
        return in_array($jenis, [self::TAHUNAN, self::BESAR], true);
    }
}
