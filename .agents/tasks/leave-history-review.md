# تاريخ الإجازات — Leave History Profile Page

This change adds a new `ViewEmployeeLeaveHistory` Filament page to the `EmployeeResource`, surfacing a per-employee leave history view: year-by-year balances, inferred grade periods, summary stats, and a full list of approved requests. The page lives at `/{record}/leave-history`, is registered in `getPages()`, and a table action generates its URL via `getUrl()`. Filtering by year range is driven by Livewire properties (`wire:model` + `wire:click`).

Watch for: (1) **confirmed** — the "إلغاء التصفية" clear-filter button chains multiple Livewire calls in a single `wire:click` attribute using semicolons; Livewire 3 does not support this — only the first call executes and the filter is never cleared. (2) **likely** — grade period inference groups by `entitled` value without regard to `leave_type_id` in the fallback path, producing nonsensical period data for employees with no `اعتيادية` balances.

**Verdict**: NEEDS_CHANGES

---

## High-level view

The year filter uses `wire:model` inputs and a `wire:click="applyFilter"` button. The "إلغاء التصفية" clear button chains three Livewire calls with semicolons in a single `wire:click` — Livewire 3 only dispatches the first, so `toYear` is never nulled and `loadData()` is never called after clearing.

Grade period inference groups all balances by `entitled` value and picks `min(year)` / `max(year)` per group. The `str_contains(..., 'اعتيادية')` guard narrows this to regular-leave balances in the happy path. In the fallback (no `اعتيادية` balances found), all leave types are merged into the same `groupBy('entitled')` — an employee with only sick-leave balances (e.g., entitled = 90 days) would show a fabricated "grade period" of 90 days/year. Beyond that, `groupBy('entitled')` collapses non-adjacent years at the same entitlement level into one period, so a promotion-then-demotion scenario silently merges two real periods.

---

<details>
<summary>Issues (3)</summary>

1. **Clear-filter button doesn't refresh data** — `wire:click="$set('fromYear', null); $set('toYear', null); applyFilter()"` chains three calls with semicolons, but Livewire 3 only dispatches the first (`$set('fromYear', null)`). The table never reloads after clearing. Fix: add a dedicated `resetFilter()` method on the PHP class that nulls both properties and calls `loadData()`, and change the button to `wire:click="resetFilter"`.

2. **Grade period grouping conflates leave types in fallback path** — when no `اعتيادية` balances exist, `$sourceForPeriods` falls back to all balance rows and groups them by `entitled` value regardless of leave type, merging sick leave, emergency leave, and regular leave rows into the same period buckets. Fix: in the fallback path, also group by `leave_type_id` before grouping by `entitled`, or display a disclaimer that inferred periods are not available for this employee.

3. **Non-consecutive year collapse** — `groupBy('entitled')` merges all years at the same entitlement level regardless of adjacency, so a promotion-then-demotion (21 → 28 → 21 days) shows as one period rather than two. The footnote `'* مستنتج من بيانات الأرصدة…'` appears in the grade periods table but not in the fallback empty-state path. Fix: surface the disclaimer unconditionally, or detect non-contiguous year gaps and split them into separate period rows.

</details>

---

<details>
<summary>Details</summary>

### Clear-filter Livewire action is broken

The "إلغاء التصفية" button:

```html
wire:click="$set('fromYear', null); $set('toYear', null); applyFilter()"
```

Livewire 3 parses `wire:click` as a single method expression, not a JavaScript statement list. Only `$set('fromYear', null)` is dispatched. After clicking: `fromYear` is null, `toYear` keeps its old value, and `loadData()` is never called — the table stays filtered.

```php
public function resetFilter(): void
{
    $this->fromYear = null;
    $this->toYear   = null;
    $this->loadData();
}
```

```html
wire:click="resetFilter"
```

### Grade period inference — fallback path correctness

```php
$regularBalances = $allBalances->filter(
    fn ($b) => str_contains($b->leaveType?->name ?? '', 'اعتيادية')
);
$sourceForPeriods = $regularBalances->isNotEmpty() ? $regularBalances : $allBalances;
```

When `$regularBalances` is empty, `$allBalances` is fed into `groupBy('entitled')`. An employee with sick-leave balances at 90 entitled days would produce a period row showing "90 يوم/سنة" — read by the user as a grade period, not a leave-type artefact.

The non-consecutive year problem is independent: `groupBy('entitled')` collapses years regardless of adjacency. For a typical career (one promotion, no demotion) the output is one period per grade, which is correct. For any employee with a demotion or re-grading it silently merges what are two distinct periods.

</details>

---

<details>
<summary>File map</summary>

- `app/Filament/Resources/EmployeeResource/Pages/ViewEmployeeLeaveHistory.php` — new Filament page class: mounts employee with `withoutGlobalScopes()`, computes balance rows, grade periods, summary stats, and approved requests; exposes Livewire filter properties.
- `app/Filament/Resources/EmployeeResource.php` — added `leave-history` route to `getPages()` and a `تاريخ الإجازات` table action with correct URL generation.
- `resources/views/filament/pages/employee-leave-history.blade.php` — RTL Blade template: employee info, year filter, summary stats, inferred grade periods, balance table, approved requests.

Full diff: `git diff main -- app/Filament/Resources/EmployeeResource/Pages/ViewEmployeeLeaveHistory.php app/Filament/Resources/EmployeeResource.php resources/views/filament/pages/employee-leave-history.blade.php`

</details>
