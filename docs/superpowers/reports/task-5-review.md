# Task 5 Review: Dependency Hygiene

**Reviewer verdicts:** SPEC: PASS · QUALITY: APPROVED
**Base:** 4509a5b → **Head:** 26ccdd4 (verified HEAD of arch-remediation)
**Diff:** 7 files, +2 −375 (deletions only + lock content-hash)

## Spec compliance (plan Task 5, step by step)

| Step | Status | Evidence |
|---|---|---|
| 1. Grep for package refs, record hits | DONE | Report BEFORE-grep lists all hits incl. two beyond expected list (config/app.php provider+alias). Honest. |
| 2. composer.json: remove 4 packages + illuminate/database wildcard | DONE — exact | Diff: `intervention/image 2.7.0`, `predis/predis ^2.0`, `laravel/sanctum ^3.3` (require), `laravel/breeze ^1.29` (require-dev), `illuminate/database: "*"` all removed. Head composer.json verified: no trace, scripts clean. |
| 3. Remove HasApiTokens from User; empty routes/api.php (keep file) | DONE | User.php head state: import + trait gone, `use` list clean (verified full file). routes/api.php: 12 lines, header docblock kept, route + unused imports removed. RouteServiceProvider still loads it. |
| 4. composer update … + audit — clean | DONE / stale expectation | Lock updated (content-hash changed, 4 package blocks removed). Audit NOT clean: 17 advisories on **kept** deps (laravel/framework, dompdf, guzzle, commonmark) — all pre-existing RISK-001 territory, explicitly deferred by plan's "Explicitly deferred" section. Plan's "clean" expectation was unachievable without the deferred upgrade. Correct handling: documented, deferred. |
| 5. artisan test + config:cache + route:cache succeed | REPORTED GREEN | 257 passed / 502 assertions; both caches built. Not re-run by reviewer (read-only mandate); statically consistent (empty api.php loads; no dangling class refs — see risk 1). |
| 6. Commit message | DONE | `chore(deps): remove unused intervention/predis/sanctum/breeze, drop database wildcard` = plan text. |

### Beyond-plan interventions — all justified

1. **config/app.php: Intervention provider + 'Image' alias removed.** Required, not optional: leaving `Intervention\Image\ImageServiceProvider::class` registered fatals boot after `composer remove`. Diff evidence: provider at former line 178, alias at former 196. Correct.
2. **config/sanctum.php deleted.** Required. Diff evidence confirms the published sanctum 3.x config executes `Sanctum::currentApplicationUrlWithPort()` eagerly at config load (deleted-file content, 'stateful' block). Keeping it = `Class "Laravel\Sanctum\Sanctum" not found` on every boot/config:cache after removal. Only correct fix. Plan's "may remain; harmless" was wrong; implementer corrected with evidence.
3. **Kernel.php commented-out Sanctum middleware line removed.** Dead comment, zero behavior change. Cleanliness; fine.
4. **bootstrap/cache stale compiled services/packages cleared + regenerated.** Necessary consequence of package removal. No Intervention/Sanctum entries remain (grep).

## Named risks

1. **No remaining references to removed packages** — PASS. Repo-wide grep (`Intervention|ImageServiceProvider|predis|Predis|auth:sanctum|createToken|HasApiTokens|Sanctum|personal_access_token`, `*.php`): only 4 hits, all benign:
   - `config/database.php:118` — `'phpredis'` substring false positive; pre-existing; correct to keep.
   - `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php:14,32` — named risk 2.
   - `resources/views/welcome.blade.php:111` + compiled copy in `storage/framework/views/` — default Laravel welcome page marketing copy hyperlink ("Sanctum" → laravel.com/docs/sanctum). Not code, not in app/config/routes/bootstrap scope. No action.
   - composer.json scripts: clean (verified full file).
2. **personal_access_tokens migration retained** — PASS. File present, untouched. Intentional per task instructions: dropping an already-shipped migration corrupts fresh-install history for zero gain; table is inert without the package.

## Quality

- Minimal diff: pure deletion + lock regeneration. No collateral edits, no reformatting noise.
- Lock hygiene: content-hash updated; exactly the 4 package blocks removed; illuminate/database had no separate lock entry (transitive via laravel/framework — stays, correct).
- User.php trait list remains sorted and consistent post-removal.
- Kernel.php api group retains framework middleware (ThrottleRequests, SubstituteBindings) — correct; group is still wired to the (now empty) api.php.
- No dead code, aliases, or config orphans left behind.

## Findings

1. **[note]** `resources/views/welcome.blade.php:111` — "Sanctum" hyperlink text in default Laravel welcome template; cosmetic, no code reference. Fix (optional): none, or replace default welcome view in a cosmetic-cleanup task.
2. **[note]** Plan Task 5 step "composer audit — clean" is stale (17 pre-existing advisories on kept deps = RISK-001 + kept dompdf/guzzle/commonmark). Fix: none in this task; Laravel 12.61+ upgrade plan owns it.
3. **[note]** `app/Exceptions/Handler.php:31` — Log facade inside reportable closure masks boot-time errors ("A facade root has not been set"), pre-existing, outside Task 5 scope, correctly flagged by implementer. Fix: file a follow-up ticket to guard the closure.
4. **[note]** Test suite / cache builds not independently re-run by reviewer (read-only mandate); implementer evidence (257 passed, both caches succeed) accepted as documented and is statically consistent with the verified head state.

No blockers. No majors.

**SPEC: PASS — QUALITY: APPROVED**
