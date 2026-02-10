# Remaining SIMPEG Security Audit Remediation Plan

## TL;DR

> **Quick Summary**: Completion of the comprehensive security and performance audit for the SIMPEG application. This plan addresses the final 6 findings (out of 29 total) focusing on N+1 query optimization, code deduplication, and authorization refactoring.
> 
> **Deliverables**:
> - [x] 4 Optimized Blade templates (Null-safe)
> - [x] 2 Optimized Models (N+1 fixes)
> - [x] 1 New Database Migration (Indexes)
> - [x] 1 New Service Class (WorkdayService)
> - [x] 2 New Policy Classes (IzinPolicy, CutiPolicy)
> - [x] 1 Deleted Controller (RegisterController)
> 
> **Estimated Effort**: Medium (2-3 hours)
> **Parallel Execution**: YES - 2 waves
> **Critical Path**: Database Migration → Model Fixes → Policy Refactoring

---

## Context

### Background
We have completed 23 of 29 security/performance findings. The remaining tasks involve medium-complexity refactoring and database optimizations that were deferred to this final phase.

### Remaining Findings
1. **S-16 (Low)**: Null-safe relation access in Blade views.
2. **P-03/P-04 (Medium)**: N+1 query fixes in `HariLibur` and `CutiBalance`.
3. **P-05 (Medium)**: Missing database indexes.
4. **Q-02 (Low)**: Code duplication (`countWorkdays`).
5. **Q-03 (Low)**: Dead code (`RegisterController`).
6. **Q-04 (Low)**: Inline authorization logic in views.

---

## Work Objectives

### Core Objective
Achieve 100% remediation of the security audit by fixing the remaining 6 architectural and performance issues.

### Concrete Deliverables
- `database/migrations/xxxx_xx_xx_xxxxxx_add_indexes_to_izin_and_cuti_tables.php`
- `app/Services/WorkdayService.php`
- `app/Policies/IzinPolicy.php`
- `app/Policies/CutiPolicy.php`
- Updated `app/Models/HariLibur.php` and `app/Models/CutiBalance.php`
- Cleaned up Blade templates in `resources/views/izin/` and `resources/views/cuti/`

### Definition of Done
- [ ] All N+1 queries resolved (verified via inspection)
- [ ] Database indexes exist for high-traffic columns
- [ ] No inline authorization logic in Blade files
- [ ] `RegisterController` is deleted
- [ ] Application passes `php artisan test` (if available) or manual verification

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Database & Cleanup):
├── Task 1: Add Database Indexes (P-05)
├── Task 2: Delete RegisterController (Q-03)
└── Task 3: Fix N+1 Queries (P-03/P-04)

Wave 2 (Refactoring):
├── Task 4: Extract WorkdayService (Q-02)
├── Task 5: Null-safe Blade Views (S-16)
└── Task 6: Extract Policies (Q-04)
```

---

## TODOs

### Wave 1: Database & Cleanup

- [x] 1. **Add Database Indexes (P-05)**
  
  **What to do**:
  - Create migration `add_indexes_to_izin_and_cuti.php`
  - Add indexes for:
    - `izin`: `status`, `atasan_pimpinan_uuid`, `pimpinan_uuid`
    - `cuti`: `status`, `jenis_cuti`, `pegawai_uuid`
  
  **References**:
  - `database/migrations/2023_08_01_000001_create_izin_table.php`
  - `database/migrations/2025_03_30_000000_create_cuti_table.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Run migration successfully
    Tool: interactive_bash
    Steps:
      1. php artisan migrate
      2. Assert exit code 0
      3. php artisan model:show Izin
      4. Assert output contains "status_index"
  ```

- [x] 2. **Delete RegisterController (Q-03)**

  **What to do**:
  - Delete `app/Http/Controllers/Auth/RegisterController.php`
  - Verify no references remain in `routes/web.php` or `routes/auth.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Verify file deletion
    Tool: interactive_bash
    Steps:
      1. rm app/Http/Controllers/Auth/RegisterController.php
      2. ls app/Http/Controllers/Auth/RegisterController.php
      3. Assert output contains "No such file"
  ```

- [x] 3. **Fix N+1 Queries (P-03/P-04)**

  **What to do**:
  - Modify `app/Models/HariLibur.php::getHariLiburByDateRange`:
    - Fetch all holidays in range ONE time using `whereBetween`
    - Check against in-memory array instead of querying in loop
  - Modify `app/Models/CutiBalance.php::checkAndUpdateBalance`:
    - Ensure it doesn't query inside loops (if used that way)
    - Eager load relationships if accessed

  **References**:
  - `app/Models/HariLibur.php`
  - `app/Models/CutiBalance.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Verify HariLibur logic remains correct
    Tool: interactive_bash
    Steps:
      1. Create test script `test_holiday.php` calling `getHariLiburByDateRange`
      2. Run `php test_holiday.php`
      3. Assert output matches expected holidays
  ```

### Wave 2: Refactoring

- [x] 4. **Extract WorkdayService (Q-02)**

  **What to do**:
  - Create `app/Services/WorkdayService.php`
  - Move `countWorkdays` logic from `IzinController` and `CutiController` to this service
  - Inject Service into Controllers or use Facade/Static method
  - Refactor Controllers to use the Service

  **References**:
  - `app/Http/Controllers/IzinController.php`
  - `app/Http/Controllers/CutiController.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Verify workday calculation
    Tool: interactive_bash
    Steps:
      1. Create test script `test_workday.php` using WorkdayService
      2. Test range with known holidays/weekends
      3. Assert correct integer returned
  ```

- [x] 5. **Null-safe Blade Views (S-16)**

  **What to do**:
  - Scan `resources/views/izin/*.blade.php` and `resources/views/cuti/*.blade.php`
  - Replace `$obj->relation->field` with `$obj->relation?->field` or `optional($obj->relation)->field`
  - Focus on: `pegawai`, `atasan_pimpinan`, `pimpinan`, `verifikator` relationships

  **References**:
  - `resources/views/izin/show.blade.php`
  - `resources/views/cuti/show.blade.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Verify View Rendering
    Tool: interactive_bash
    Steps:
      1. php artisan view:cache
      2. Assert exit code 0 (no syntax errors)
  ```

- [x] 6. **Extract Policies (Q-04)**

  **What to do**:
  - Create `app/Policies/IzinPolicy.php` and `app/Policies/CutiPolicy.php`
  - Move inline auth logic (`auth()->user()->hasRole(...)`) from Blade files to Policy methods (`view`, `update`, `delete`)
  - Register Policies in `AuthServiceProvider`
  - Update Blade views to use `@can('update', $izin)`

  **References**:
  - `resources/views/izin/show.blade.php`
  - `app/Providers/AuthServiceProvider.php`

  **Agent-Executed QA Scenarios**:
  ```
  Scenario: Verify Policy Registration
    Tool: interactive_bash
    Steps:
      1. php artisan model:show Izin
      2. Check if Policy is listed (if supported) OR
      3. Create route to test `Gate::inspect('update', $izin)`
  ```

---

## Final Verification
- [ ] Run `php artisan optimize:clear`
- [ ] Run `php artisan migrate:status`
- [ ] Manual smoke test of Izin/Cuti flows
