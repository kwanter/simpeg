# Learnings & Conventions

- **Blade Null-Safety**: Use `?->` operator for all relationship access in Blade templates to prevent 500 errors when data is missing.
- **N+1 Prevention**: Always batch-fetch data outside of loops. For date ranges, query the entire range at once using `whereBetween`.
- **Database Indexes**: Add indexes for columns frequently used in `where`, `orderBy`, or foreign keys that aren't primary keys.
- **Service Layer**: Extract business logic (like workday calculation) into dedicated Service classes (e.g., `WorkdayService`) to stay DRY.
- **Dead Code**: Aggressively remove unused controllers like `RegisterController` when functionality is disabled.
- **Policies**: Use Laravel Policies for authorization instead of inline checks in Blade/Controllers.

- **N+1 Optimization with Date Ranges**: In HariLibur, we switched from per-day queries to a single whereBetween query, mapping results to a keyed array for O(1) lookups during iteration.
- **Bulk Update Pattern**: For CutiBalance, we implemented a bulk method that accepts an array of UUIDs, reducing the number of queries in loops from N*2 to 1.
- **Efficient Indexing**: Added index to columns used in range queries (hari_libur.tanggal) to ensure the batch fetch is actually fast.
