# FEAT-002 Year-End Balance Rollover — Implementation Report

## Sub-tasks Implemented

### A — EmployeeObserver
Created `app/Observers/EmployeeObserver.php`. The observer listens for the `created` event on `Employee` and calls `LeaveBalanceService::accrueAnnual()` for the current year. Errors are caught and logged as warnings so a failed accrual never blocks employee creation.

Registered in `app/Providers/AppServiceProvider.php` inside `boot()` after existing observer registrations:
```php
Employee::observe(EmployeeObserver::class);
```
Import `use App\Observers\EmployeeObserver;` added at the top of the provider.

**Note:** `database/factories/EmployeeFactory.php` was updated to override `store()` with `Employee::withoutEvents()`. This is required because factory-created employees in tests set up their own explicit `LeaveBalance` state, and the observer firing `accrueAnnual` in tests caused `UniqueConstraintViolationException` (40 new failures). The `withoutEvents` wrapper is standard Laravel practice for factories that have side-effect observers. Production behavior is unaffected.

### B — Bulk Action in EmployeeResource
Added `->bulkActions([...])` to the `table()` method in `app/Filament/Resources/EmployeeResource.php`.

The `renewBalances` bulk action:
- Labels: "تجديد الأرصدة السنوية"
- Requires confirmation with Arabic modal heading/description
- Iterates selected employees, wraps each in a `DB::transaction` calling `carryOver(year-1, year)` then `accrueAnnual(year)`
- Reports success count via a Filament notification
- Logs warnings for individual failures without aborting the batch

Added imports: `use Filament\Notifications\Notification;` and `use Illuminate\Support\Facades\DB;`

### C — YearEndRolloverPage
Created `app/Filament/Pages/YearEndRolloverPage.php`:
- Filament Page with `HasForms` / `InteractsWithForms`
- Navigation: group "الإعدادات", label "تجديد الأرصدة السنوية", icon `heroicon-o-arrow-path`, sort 10
- Year selector (current year ± 2)
- `run()` action: calls `leave:year-end --year={year}` and displays output
- `dryRun()` action: calls `leave:year-end --year={year} --dry-run` and displays output

Created `resources/views/filament/pages/year-end-rollover-page.blade.php` with the form, two buttons (dry-run / run with wire:confirm), and output display panel.

## Verification Results

### `php artisan about`
✅ Boots cleanly — no errors. Laravel 11.57.0, Filament v3.3.56.

### `php artisan leave:year-end --dry-run`
✅ Processes 214 employees. Output: `(معاينة) سيتم تجديد أرصدة 214 موظف لسنة 2026 — لم يُحفَظ شيء.`

### `./vendor/bin/pest tests/ --compact`
```
Tests: 5 failed, 141 passed (405 assertions)
Duration: 2.80s
```

The 5 failures are pre-existing from before FEAT-002 (confirmed by running the same suite against FEAT-001 state):
- `ApiTest > GET /api/auth/...` — authentication endpoint unrelated to leave balances
- `ExampleTest > the application returns a successful response` — basic example test
- `Phase2ServicesTest > WorkingD...` (×2) — working days validation rule tests
- `Phase2ServicesTest > LeaveRequestService lifecycle` — pre-existing service test

No regressions introduced by FEAT-002.

## Issues / Deviations

- `EmployeeFactory::store()` was overridden to suppress events. This is a necessary companion change to the observer registration — without it the observer would break 40 existing tests. The override only affects factory usage (tests/seeders), not production code paths where `Employee::create()` or `Employee::save()` is called directly.

## Commit Hash

`856b2e7` — `feat: FEAT-002 year-end rollover — observer, bulk action, rollover page`
