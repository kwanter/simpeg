# Task 3 Implementer Report: pegawai.user_uuid FK (replace nip join)

**Status: DONE_WITH_CONCERNS** (MySQL live-migrate unverifiable from this session — see Concerns)

## Implemented

1. **Migration** `database/migrations/2026_08_23_000000_add_user_uuid_to_pegawai_table.php`
   - `foreignUuid('user_uuid')->nullable()->after('nip')`
   - Backfill: `UPDATE pegawai SET user_uuid = (SELECT u.uuid FROM users u WHERE u.nip = pegawai.nip LIMIT 1) WHERE user_uuid IS NULL`
     - Correlated subquery instead of the plan's `UPDATE ... JOIN`: portable across MySQL and SQLite (tests run sqlite :memory: via phpunit.xml). Equivalent semantics; nip is unique on pegawai and app-validated unique on users, so at most one user matches.
     - Idempotent: `WHERE user_uuid IS NULL` guard; unique index + FK added AFTER backfill.
     - Handles both edges: pegawai without user → stays NULL; user without pegawai → untouched.
   - `unique('user_uuid')` + FK `user_uuid → users.uuid nullOnDelete()`
   - `down()`: drops FK (skipped on SQLite — Blueprint throws), unique index, column.
2. **Models**
   - `Pegawai::user()` → `belongsTo(User::class, 'user_uuid', 'uuid')` (was nip/nip)
   - `User::pegawai()` → `hasOne(Pegawai::class, 'user_uuid', 'uuid')` (new)
   - `user_uuid` added to Pegawai $fillable. nip columns untouched.
3. **Auth-driven nip lookups replaced** (all `Pegawai::where('nip', <auth user>->nip)` sites):
   - `CutiController`: index/create/store/verifikasiPimpinan/verifikasiAtasanPimpinan/prosesVerifikasiPimpinan/prosesVerifikasiAtasanPimpinan/showBalance/updateBalance → `$user->pegawai` / `$user->pegawai?->uuid` / `$user->pegawai()->firstOrFail()` (store keeps 404 semantics)
   - `IzinController`: create/store(`pegawai()->firstOrFail()->uuid`)/prosesVerifikasiAtasan/prosesVerifikasiPimpinan/createKeluarKantor/createTidakMasuk
   - `CutiPolicy`/`IzinPolicy` `pegawaiUuidFor()` → `$user->pegawai?->uuid`
   - `IzinQueryService::forUser()` → `$user->pegawai?->uuid`
4. **Ownership nip joins replaced too** (same fragility the FK removes — nip rename would break access):
   - `CutiPolicy::isOwner` / `IzinPolicy::isOwner` / `IzinPolicy::update`: `$user->nip === $model->pegawai?->nip` → `$user->pegawai?->uuid === $model->pegawai_uuid`
5. **PegawaiController::detail()**: `User::where('nip', $pegawai->nip)->first()` → `$pegawai->user` (uses the swapped relation; keeps detail page correct after nip renames)
6. **Provisioning keeps working**: `UserController::store()` now links `Pegawai::where('nip', $user->nip)->update(['user_uuid' => $user->uuid])` after user creation (nip is the admin-entered identifier at provisioning time; everything else joins via FK).
7. **Test infra**: `SimpegTestCase::createUserWithRole()` sets `user_uuid` on the pegawai it creates (without this every feature test broke — helper simulated provisioning by nip only).
8. Unused imports removed (Pegawai in policies/IzinQueryService, User in PegawaiController).

## TDD Evidence

**RED** — `php artisan test tests/Feature/UserPegawaiRelationTest.php`:
```
Tests: 3 failed, 2 passed (2 assertions)
FAILED ... > auth user pegawai...  QueryException SQLSTATE[HY000]: table pegawai has no column named user_uuid
FAILED ... > renaming…        QueryException (same)
FAILED ... > pegawai u…        QueryException (same)
```
(2 passing were the null-relation tests — Eloquent returns null for unknown relation access, so they pass trivially pre-implementation.)

**GREEN** — `php artisan test`:
```
PASS  Tests\Feature\UserPegawaiRelationTest
  ✓ auth user pegawai returns linked pegawai
  ✓ user without pegawai has null relation
  ✓ renaming pegawai nip keeps link          ← the FK point
  ✓ pegawai user inverse returns linked user
  ✓ pegawai without user has null inverse
Tests: 257 passed (502 assertions)   ← 252 baseline + 5 new
Duration: 10.03s
```

**Migration verification** (MySQL dev DB unreachable — OrbStack/docker cannot start under this session's sandbox; `DB_HOST=mysql` DNS fails):
- Full `php artisan migrate` on a scratch SQLite DB: all 24 migrations DONE incl. `2026_08_23_000000_add_user_uuid_to_pegawai_table`.
- Backfill unit-verified with seeded rows (u1/p1 nip-matched, u2 no pegawai, p2 no user): p1→u1, p2→NULL. ✔
- `migrate:rollback --step=1`: DONE, column GONE. ✔
- Migration also executed 250+ times via RefreshDatabase in the green suite.

## Files Changed

- `database/migrations/2026_08_23_000000_add_user_uuid_to_pegawai_table.php` (new)
- `tests/Feature/UserPegawaiRelationTest.php` (new)
- `app/Models/Pegawai.php`, `app/Models/User.php`
- `app/Http/Controllers/CutiController.php`, `IzinController.php`, `PegawaiController.php`, `UserController.php`
- `app/Policies/CutiPolicy.php`, `app/Policies/IzinPolicy.php`
- `app/Services/IzinQueryService.php`
- `tests/SimpegTestCase.php`

## grep Results

`grep -rn "where('nip'" app/` → **1 hit**:
```
app/Http/Controllers/UserController.php:86: Pegawai::where('nip', $user->nip)->update(['user_uuid' => $user->uuid]);
```
Intentional: provisioning-time link establishment (admin-entered nip), not an auth-driven lookup. All auth-driven nip lookups gone; policies' nip ownership comparisons also converted to uuid.

## Concerns

1. **MySQL live-migrate not run**: OrbStack daemon cannot start under the session sandbox, so `php artisan migrate` against the dev MySQL (host `mysql`) was impossible here. The migration was verified on SQLite only. SQL is standard MySQL (correlated-subquery UPDATE, unique index, FK, after()); backfill/FK semantics unit-verified. **Run `php artisan migrate` against MySQL when the environment allows.**
2. `users.nip` has no DB-level unique index (app validation only). If two users ever shared a nip, the backfill would pick an arbitrary one (LIMIT 1). Pre-existing data-quality edge, unchanged behavior class.
3. `UserController::update()` can change a user's nip; under the FK regime the pegawai link no longer follows nip changes (by design — that is the FK point). If admins previously used "change user nip" as a reassignment mechanism, they now must not; consider an admin tool later. Out of scope here.
