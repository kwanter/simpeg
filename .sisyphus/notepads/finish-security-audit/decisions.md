# Architectural Decisions

- **Bulk Methods in Models**: Introduced `bulkCheckAndUpdateBalance` in `CutiBalance` model to centralize batch logic rather than having it scattered in controllers/commands.
- **Single-Query Per-User Optimization**: Even for single-user calls, `checkAndUpdateBalance` now fetches current and previous year in one query (`whereIn(['year', 'year-1'])`) instead of two separate queries.
- **Migration Consolidation**: Added the `hari_libur.tanggal` index to the today's migration (`2026_02_10_004802_add_indexes_to_izin_and_cuti_tables.php`) to avoid migration bloat.
