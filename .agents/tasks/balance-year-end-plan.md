# Implementation Plan — Year-End Leave Balance Rollover

## Decisions

- **Two features, not five**: Items 1+2+3 (command + scheduler + carry-over fix) form one cohesive backend feature (`FEAT-001`). Items 4+5 (observer + Filament UI) form one cohesive frontend/trigger feature (`FEAT-002`). Splitting further adds overhead without value.
- **carryOver() idempotent guard instead of schema change**: The investigation recommends either a new `carry_over_source_year` column or an idempotency guard. We use the guard (zero migration cost, achieves the same protection), zeroing `sourceBalance.carried_over` after copy inside the same transaction. This also satisfies the "carried-over days can't roll forward again" requirement without touching the DB schema.
- **BulkAction placement**: `table()` in `EmployeeResource` has no existing `->bulkActions()` call, so we add one alongside the existing `->actions()` chain. The Filament 3 `Tables\Table::bulkActions()` method is the correct entry point.
- **YearEndRolloverPage view pattern**: All existing Filament pages use `<x-filament-panels::page>` with `wire:submit="save"` and `{{$this->form}}`. We follow the same pattern with a `run()` action instead of `save()`.
- **EmployeeObserver namespace**: Existing observers live in `App\Observers\`. We create `EmployeeObserver` there, registered in `AppServiceProvider::boot()` after the existing three observer registrations.

---

## Plan

- [ ] 1. **Create `AnnualLeaveRollover` Artisan command**
      Signature: `leave:year-end {--year= : السنة المستهدفة (افتراضي: السنة الحالية)} {--dry-run : معاينة بدون حفظ}`.
      Logic: load all active employees with `leaveBalances` and `entitlementGrade`; for each employee wrap `carryOver($emp, $year-1, $year)` + `accrueAnnual($emp, $year)` in a per-employee `DB::transaction`; `--dry-run` wraps the entire outer loop in a transaction that is rolled back at the end and prints a preview table; progress bar via `$this->withProgressBar()`; per-employee try/catch that logs and continues; final `$this->info("تم تجديد أرصدة {$count} موظف لسنة {$year} بنجاح.");`. Follow the style of `NotifyOverdueRequests.php` (namespace, return `Command::SUCCESS`).
      Files: `app/Console/Commands/AnnualLeaveRollover.php` (create)
      Verify: `php artisan leave:year-end --dry-run --year=2025` exits 0 and prints the preview table without persisting any DB rows.

- [ ] 2. **Register scheduler in `routes/console.php`**
      Add `use Illuminate\Support\Facades\Schedule;` at the top, then append:
      ```php
      Schedule::command('leave:year-end')
          ->yearlyOn(1, 1, '00:01')
          ->withoutOverlapping()
          ->runInBackground()
          ->appendOutputTo(storage_path('logs/year-end-rollover.log'));
      ```
      The existing file only has `Artisan::command('inspire', ...)` — add after it.
      Files: `routes/console.php` (modify)
      Verify: `php artisan schedule:list` shows `leave:year-end` with a yearly trigger.

- [ ] 3. **Enforce carry-over expiry in `LeaveBalanceService::carryOver()`**
      Two changes inside the existing `carryOver()` method:
      a) Idempotent guard at the top of the `foreach` loop body: if `$toBalance->carried_over > 0`, `continue` (skip this balance — already carried this year).
      b) Inside the existing `DB::transaction` closure, after `$toBalance->increment('carried_over', $carryDays)`, add:
         ```php
         $bal->refresh();
         $bal->carried_over = 0;
         $bal->save();
         ```
      This prevents the source year's remaining carried-over from being re-rolled forward next cycle.
      Files: `app/Services/LeaveBalanceService.php` (modify)
      Verify: `php artisan test --filter=Phase2ServicesTest` — all existing tests pass (the idempotency guard must not break the normal first-run path).

- [ ] 4. **Create `EmployeeObserver` and register it**
      `created()` hook: resolve `LeaveBalanceService` from the container, call `$service->accrueAnnual($employee, now()->year)` wrapped in `try/catch (\Throwable $e)` that calls `Log::warning(...)`.
      Register in `AppServiceProvider::boot()` after the existing three observer lines:
      ```php
      Employee::observe(EmployeeObserver::class);
      ```
      Add the import `use App\Observers\EmployeeObserver;` at the top of `AppServiceProvider.php`.
      Files:
      - `app/Observers/EmployeeObserver.php` (create)
      - `app/Providers/AppServiceProvider.php` (modify — add import + `boot()` line)
      Verify: `php artisan test --filter=Phase2ServicesTest` still passes; manually confirm a `php artisan tinker` `Employee::factory()->create(...)` produces a `leave_balances` row for the current year (or run `php artisan test` overall and check no regressions).

- [ ] 5. **Add bulk action `renewBalances` to `EmployeeResource` table**
      In `EmployeeResource::table()`, add `->bulkActions([...])` after `->actions([...])`.
      Bulk action: `BulkAction::make('renewBalances')` with label `تجديد الأرصدة السنوية`, icon `heroicon-o-arrow-path`, color `warning`, `->requiresConfirmation()`.
      Action closure: loop `$records`, wrap per-employee in `DB::transaction` calling `$service->carryOver($emp, now()->year - 1, now()->year)` + `$service->accrueAnnual($emp, now()->year)`, count successes, send Filament `Notification::make()->title("تم تجديد الأرصدة لـ {$count} موظف")->success()->send()`.
      Add imports: `Filament\Tables\Actions\BulkAction`, `App\Services\LeaveBalanceService`, `Illuminate\Support\Facades\DB`, `Filament\Notifications\Notification`.
      Files: `app/Filament/Resources/EmployeeResource.php` (modify)
      Verify: `php artisan test` passes; load the employees list in browser, select rows, confirm the bulk action appears in the bulk-actions dropdown.

- [ ] 6. **Create `YearEndRolloverPage` Filament page + Blade view**
      Page class: navigation group `الإعدادات`, label `تجديد الأرصدة السنوية`, icon `heroicon-o-arrow-path`.
      Form: `Select::make('year')` default `now()->year` with options `[now()->year - 1 => ..., now()->year => ...]`; optional `Select::make('organization_id')` with `Organization::pluck('name','id')`.
      Action method `run()`: calls `Artisan::call('leave:year-end', ['--year' => $this->data['year']])`, captures output with `Artisan::output()`, stores in `$this->output` public property, sends Filament notification on success.
      Blade view: follow the pattern of `change-password-page.blade.php` — `<x-filament-panels::page>` wrapping `<form wire:submit="run">`, `{{$this->form}}`, a submit button, and a `@if($this->output)` block showing the command output in a `<pre>` tag.
      Files:
      - `app/Filament/Pages/YearEndRolloverPage.php` (create)
      - `resources/views/filament/pages/year-end-rollover-page.blade.php` (create)
      Verify: `php artisan route:list` (or `php artisan filament:check`) shows the page; navigate to it in browser and submit a year — the page shows output without errors.

---

## Dependency Order

1 → 2 (scheduler depends on command existing)
1 → 3 (modify the same service the command calls — do independently of command, but before testing command end-to-end)
4 (independent — observer only touches Employee + LeaveBalanceService)
5 (independent — only touches EmployeeResource)
6 (depends on 1, because it calls the Artisan command)

Safe execution order: **3 → 1 → 2 → 4 → 5 → 6** (service fix first so the command is correct from the start).
