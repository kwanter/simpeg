# Issues & Gotchas

- **N+1 in Date Ranges**: `HariLibur::getHariLiburByDateRange` was querying inside a loop.
- **Blade Relations**: `izin->pegawai` can be null if the employee is deleted, crashing the view.
- **RegisterController**: Unused but present, causing confusion.
- **Permissions**: `izin.verifikasi_atasan` and similar fields need indexes for dashboard performance.
