# Implementation Plan — تاريخ الإجازات (Leave History Profile)

## Codebase Findings

- **Framework**: Laravel 11.31 + Filament 3.x, PHP 8.2.
- **Custom page pattern**: The project uses Blade-backed Filament pages (`Page` with `$view` + a `.blade.php` under `resources/views/filament/pages/`). The `ReportsPage` is the canonical example. For record-aware pages the project uses `ViewRecord` (see `ViewLeaveRequest`). The right pattern for a record page attached to `EmployeeResource` is to extend `Filament\Resources\Pages\Page` and use the `InteractsWithRecord` trait — this keeps us inside the Resource routing machinery without forcing an Infolist layout.
- **Global scopes**: Both `Employee` and `LeaveRequest` carry `OrganizationScope`. The admin-facing leave-history page must call `withoutGlobalScopes()` on all queries, mirroring `LeaveRequestResource::getEloquentQuery()`.
- **`LeaveBalance`**: no global scope. Fields: `employee_id`, `leave_type_id`, `year`, `entitled`, `carried_over`, `used`; computed `remaining` = `entitled + carried_over − used` (never stored).
- **`LeaveRequest` APPROVED status value**: `'approved'` (from `LeaveStatus::APPROVED->value`).
- **View directory**: `resources/views/filament/pages/` — all existing custom pages (`reports-page`, `edit-my-profile`) drop their Blade files here.
- **`EmployeeResource::getPages()`** currently returns `index`, `create`, `edit`. The new `leave-history` key must be added.
- **Table action pattern**: `Tables\Actions\Action::make('name')->url(fn(...) => ...)` — used for external URL navigation in the existing codebase (e.g., print/download actions in `ViewLeaveRequest`).

---

## Implementation Plan

- [ ] 1. Create the custom Filament page class `ViewEmployeeLeaveHistory`.

      This class extends `Filament\Resources\Pages\Page`, adds the `Filament\Resources\Pages\Concerns\InteractsWithRecord` trait, and declares `protected static string $resource = EmployeeResource::class`. It computes all view data inside `mount()` (called once on page load) and exposes public properties for the Blade template. All Employee and LeaveRequest queries use `withoutGlobalScopes()`.

      **Properties to expose:**
      - `public Employee $employee` — the resolved record (loaded via `resolveRecord(int $key)` with `withoutGlobalScopes()->with('entitlementGrade')`)
      - `public int $serviceYears` — `work_start_date ?? hire_date` → years to today using `Carbon::diffInYears()`
      - `public \Illuminate\Support\Collection $balances` — all `LeaveBalance` rows for this employee, eager-loaded with `leaveType`, sorted by `year ASC, leaveType.name ASC`; filtered by `from_year`/`to_year` filter values when set
      - `public \Illuminate\Support\Collection $gradePeriods` — inferred from balances: group by `leave_type_id = regular leave type` balances, collect consecutive years with the same `entitled` value into period objects `{entitled, from_year, to_year, years_count}`; if no regular-type balances exist, fall back to grouping all balance rows by unique `entitled` values
      - `public int $totalEntitled` — `$balances->sum('entitled')`
      - `public int $totalUsed` — `$balances->sum('used')`
      - `public int $totalRemaining` — `$balances->sum(fn($b) => $b->remaining)`
      - `public int $approvedCount` — count of `LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->where('status', LeaveStatus::APPROVED->value)->count()`
      - `public \Illuminate\Support\Collection $approvedRequests` — approved leave requests for this employee, loaded with `leaveType`, sorted by `start_date DESC`
      - `public int $fromYear` — filter lower bound, defaults to `(int)($balances->min('year') ?? now()->year)`
      - `public int $toYear` — filter upper bound, defaults to `(int)now()->year`

      **Methods:**
      - `mount(int | string $record): void` — resolves employee, reads `request('from_year')` / `request('to_year')` query params, calls private `loadData()`.
      - `private loadData(): void` — runs all the queries above and fills the public properties.
      - `public static function route(string $path): Pages\PageRegistration` — inherited; called as `Pages\ViewEmployeeLeaveHistory::route('/{record}/leave-history')`.
      - `protected static string $view = 'filament.pages.employee-leave-history'` — the Blade template path.
      - `public function getTitle(): string` — returns `'تاريخ الإجازات — '.$this->employee->full_name`.

      **File to create:**
      `/Users/mohamedgaber/projects/vaccancy/leave-management-system/app/Filament/Resources/EmployeeResource/Pages/ViewEmployeeLeaveHistory.php`

      **Verify:** `php artisan route:list --name=filament` in the project root — confirms the new route appears without errors. The application must load without exceptions (`php artisan view:clear && php artisan config:clear`).

---

- [ ] 2. Create the Blade view for the leave-history page.

      The template wraps everything in `<x-filament-panels::page>` (same as `reports-page.blade.php`) with `dir="rtl"`. It uses Filament's `<x-filament::section>` and `<x-filament::card>` Blade components, plus plain Tailwind utility classes consistent with the existing `balance-badge.blade.php` style (no new CSS frameworks).

      **Sections to render (in order):**

      **A. Employee Info Header** — one `<x-filament::section>` with heading `'بيانات الموظف'`:
      - Full name, employee code, job title (in a 3-column grid).
      - Entitlement grade name + yearly_days (show `'—'` when null).
      - Hire date formatted `d F Y`, work start date formatted `d F Y`.
      - Total years of service: `$serviceYears` + ` سنة`.

      **B. Year filter form** — a plain `<form method="GET">` (no Livewire needed; page reloads with query params) with two `<input type="number">` fields for `from_year` / `to_year` pre-filled with `$fromYear` / `$toYear` and a submit button styled with `<x-filament::button>`.

      **C. Year-by-year balance table** — one `<x-filament::section>` with heading `'أرصدة الإجازات'`. An HTML `<table>` with columns: السنة | نوع الإجازة | المستحق | المُرحَّل | المستخدم | المتبقي. Rows come from `@foreach ($balances as $balance)`. Show an empty-state message when `$balances->isEmpty()`.

      **D. Inferred grade periods** — one `<x-filament::section>` with heading `'الفترات الوظيفية المستنتجة'`. A table with columns: من سنة | إلى سنة | عدد السنوات | الأيام المستحقة/سنة. Rows from `@foreach ($gradePeriods as $period)`. Add a small note below: `'مستنتج من بيانات الأرصدة — لا يعكس تغييرات الدرجة الرسمية'`. Show `'—'` when `$gradePeriods->isEmpty()`.

      **E. Summary statistics** — one `<x-filament::section>` with heading `'الإجماليات'`. Four stat cards in a responsive grid: إجمالي سنوات الخدمة (`$serviceYears`) | إجمالي المستحق (`$totalEntitled`) | إجمالي المستخدم (`$totalUsed`) | إجمالي المتبقي (`$totalRemaining`) | عدد الإجازات المعتمدة (`$approvedCount`).

      **F. Approved leave requests table** — one `<x-filament::section>` with heading `'الإجازات المعتمدة'`. Columns: رقم الطلب | نوع الإجازة | من | إلى | الأيام | السنة (extracted as `$request->start_date->year`). Dates formatted `d/m/Y`. Show empty-state when none exist.

      **File to create:**
      `/Users/mohamedgaber/projects/vaccancy/leave-management-system/resources/views/filament/pages/employee-leave-history.blade.php`

      **Verify:** Navigate to `http://localhost/admin/employees/{id}/leave-history` in a browser (or `php artisan serve` equivalent) — the page renders all six sections without Blade or PHP errors.

---

- [ ] 3. Register the new page in `EmployeeResource::getPages()` and add a table action.

      **Modify `getPages()`** to add the `leave-history` route:
      ```php
      'leave-history' => Pages\ViewEmployeeLeaveHistory::route('/{record}/leave-history'),
      ```

      **Add a table action** in `EmployeeResource::table()` → `->actions([...])`, after the existing `EditAction` and before `DeleteAction`:
      ```php
      Tables\Actions\Action::make('leaveHistory')
          ->label('تاريخ الإجازات')
          ->icon('heroicon-o-clock')
          ->color('info')
          ->url(fn (Employee $record): string => EmployeeResource::getUrl('leave-history', ['record' => $record])),
      ```

      **File to modify:**
      `/Users/mohamedgaber/projects/vaccancy/leave-management-system/app/Filament/Resources/EmployeeResource.php`

      **Verify:** `php artisan route:list --name=filament | grep leave-history` — the route `employees/{record}/leave-history` appears. Visiting the employee list in the browser shows the new action button in each row.

---

## Assumptions

- **Grade period inference**: The plan groups `LeaveBalance` rows for leave type code `'regular'` (the standard `اعتيادية` type) by consecutive years with the same `entitled` value, since this is the value that changes when an employee is promoted. If no balances of type `regular` exist for an employee, fall back to all balances grouped by unique `entitled` values. This does not require a `grade_history` table.
- **Service years**: Calculated from `work_start_date` if set; otherwise `hire_date`. If both are null, display `0`.
- **`withoutGlobalScopes()`**: Applied in `ViewEmployeeLeaveHistory::resolveRecord()` and in the `loadData()` queries for both `Employee` (via `Employee::withoutGlobalScopes()`) and `LeaveRequest` (via `LeaveRequest::withoutGlobalScopes()`). `LeaveBalance` has no global scope so it can be accessed normally via the relation.
- **Year filter**: Implemented as a plain GET form (no Livewire reactivity needed) since the page already does a full PHP load per request.
- **No policy guard on this page**: Following the pattern of `ReportsPage` which relies on `canViewAny()` from the resource — access to this page inherits from `EmployeeResource::canViewAny()`. If stricter access control is needed later, add `canAccess(): bool` to `ViewEmployeeLeaveHistory`.
