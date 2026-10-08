# Leave Balance System — Investigation Report

## Summary

The leave balance **data model, service logic, and Filament UI are fully implemented**. The schema has `year`, `entitled`, `carried_over`, and `used` columns. `LeaveBalanceService` has grade-based entitlement calculation, carry-over, annual accrual, deduction on approval, and refund on rejection/cancellation. The Filament employee view shows a dedicated "رصيد الإجازات" tab and a balance column in the employees list.

**The single missing piece is operational**: there is no Artisan command that actually triggers `carryOver()` + `accrueAnnual()` at year-end for all employees. These methods exist in the service but are never called automatically (no scheduled command, no observer, no seeder). They are tested in the test suite but only invoked manually in test helpers.

---

## Question-by-Question Evidence

### 1. LeaveBalance model & migration

**File:** `app/Models/LeaveBalance.php`  
**Migration:** `database/migrations/2026_10_05_000004_create_leave_balances_tables.php`

Columns in `leave_balances`:
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `employee_id` | FK → employees | cascade delete |
| `leave_type_id` | FK → leave_types | |
| `year` | unsignedSmallInteger | ✅ year-based tracking |
| `entitled` | decimal(5,1) | annual entitlement for this year |
| `carried_over` | decimal(5,1) | ✅ days brought from previous year |
| `used` | decimal(5,1) | total consumed days |
| `timestamps` | | |

Unique constraint: `(employee_id, leave_type_id, year)` — one row per employee per type per year.

A companion `leave_balance_transactions` table acts as a ledger with `type` in `[accrual, deduction, refund, carryover, adjustment]`.

Computed attribute `remaining = max(0, entitled + carried_over - used)` — never stored, always derived.

**Conclusion:** ✅ Full schema exists. `year` column present. `carried_over` concept present.

---

### 2. LeaveBalanceService

**File:** `app/Services/LeaveBalanceService.php`

| Method | Description | Status |
|---|---|---|
| `getOrCreateBalance($emp, $lt, $year)` | Creates balance row if missing; sets `entitled` from grade for `regular` type | ✅ |
| `deduct($balance, $days, $request, $user)` | Increments `used`, writes DEDUCTION transaction, row-locked | ✅ |
| `refund($balance, $days, $request, $user)` | Decrements `used` (floored at 0), writes REFUND transaction | ✅ |
| `adjust($balance, $days, $note, $user)` | Admin manual adjustment: +days → entitled, −days → used | ✅ |
| `carryOver($emp, $fromYear, $toYear)` | Carries remaining days from `fromYear` to `carried_over` in `toYear` | ✅ exists, **not called automatically** |
| `accrueAnnual($emp, $year)` | Sets `entitled` on all active leave types with `yearly_entitlement > 0` | ✅ exists, **not called automatically** |

**Grade-based entitlement:** `getOrCreateBalance` and `accrueAnnual` both call `$employee->regularLeaveEntitlement()` for `code === 'regular'` types. This method checks `birth_date >= 50` for the over-50 override, otherwise reads `entitlementGrade.yearly_days`.

**Carry-over config:** `config('leave.carry_over_max_days')` defaults to `null` (no cap). Set in `config/leave.php`.

**Deduction on approval:** Called from `LeaveRequestService` at lines 367 and 495 via `getOrCreateBalance` + `deduct`.

**Year isolation:** `carryOver` explicitly only copies to `carried_over` of the next-year row. The current-year `entitled` is set fresh by `accrueAnnual`. Semantically, carried-over days can only be used in the year they are carried into (because `carryOver` only populates next year's `carried_over` once, not rolled forward again).

**Limitation on "can't use previous year's carried days in a new year":** The data model does NOT enforce this restriction automatically. Once days are in `carried_over` for year N, they behave identically to `entitled` days — there is no flag distinguishing them as "expire at end of year N". If `carryOver` is run again for year N → N+1, `remaining` would include whatever is left of those already-carried days. A `carry_over_used` column or an expiry mechanism is absent.

**Conclusion:** ✅ All CRUD and year-end logic exists. ❌ No automatic trigger. ❌ No hard expiry for carried-over days within the year they were carried into.

---

### 3. EntitlementGrade

**Files:** `app/Enums/EntitlementGrade.php` (PHP enum, used in tests/legacy) + `app/Models/EntitlementGrade.php` (Eloquent model, authoritative at runtime) + `database/seeders/EntitlementGradeSeeder.php`

The enum and DB model both define the same grade codes. The DB model is the runtime source; the enum's `yearlyDays()` is used in tests for convenience.

Active grades seeded (from `EntitlementGradeSeeder`):

| Code | Name | Days/Year |
|---|---|---|
| `teacher` | معلم | 28 |
| `teacher_first` | معلم أول | 30 |
| `teacher_first_a` | معلم أول (أ) | 35 |
| `teacher_expert` | معلم خبير | 40 |
| `teacher_senior` | كبير معلمين | 45 |
| `admin_4` | إداري الدرجة الرابعة | 28 |
| `admin_3` | إداري الدرجة الثالثة | 30 |
| `admin_over50` | موظف (فوق 50 سنة) | 50 |

Inactive grades also in seeder: `teacher_assistant`, `guidance`, `guidance_first`, `guidance_general`, `admin_2`, `admin_1`, `admin_senior`.

**Conclusion:** ✅ Grade-based annual entitlement fully defined in DB + enum + seeder.

---

### 4. LeaveType model

**File:** `app/Models/LeaveType.php`

Relevant fields:
- `deducts_balance` (boolean) — controls whether this type draws from the balance pool
- `yearly_entitlement` (integer) — default days/year for non-regular types
- `max_days_per_request` (nullable integer) — per-request cap
- `is_active` (boolean)
- `code` (string) — `'regular'`, `'casual'`, `'sick'`, etc.

**Conclusion:** ✅ `deducts_balance` exists. `LeaveBalanceService.carryOver()` filters on it (`$bal->leaveType->deducts_balance && $bal->remaining > 0`).

---

### 5. Employee model

**File:** `app/Models/Employee.php`

- `entitlement_grade` column: string FK → `entitlement_grades.code`. Cast comment says it's a raw string (not enum cast).
- `entitlementGrade()` BelongsTo relationship to `App\Models\EntitlementGrade`.
- `regularLeaveEntitlement()` method: checks age ≥ 50 first (returns 50 days), then reads `entitlementGrade.yearly_days`, falls back to 28.
- `balanceFor(int $leaveTypeId, ?int $year)` helper method.
- `leaveBalances()` HasMany relationship.

**Conclusion:** ✅ Grade-based entitlement method exists on the model. No balance attributes are stored on employees — all in `leave_balances` table.

---

### 6. Filament UI

**File:** `app/Filament/Resources/EmployeeResource.php`

**Employee list table:**
- Column `balance_badge` uses Blade view `filament.columns.balance-badge`
- View (`resources/views/filament/columns/balance-badge.blade.php`) shows per-type remaining/entitled as colored pills: e.g. `إجازة اعتيادية: 25/30`

**Employee view page (Infolist):**
- Tab "رصيد الإجازات" with section "رصيد السنة الحالية — {year}" 
- `RepeatableEntry` showing for each balance: نوع الإجازة | المستحق | المُرحَّل | المستخدم | المتبقي
- Second section "الإجازة الاعتيادية — ملخص الاستحقاق" showing: المستحق النظامي هذه السنة | المتبقي من الإجازة الاعتيادية (with color-coded badge: green/red/gray)

**Conclusion:** ✅ Balance display is fully implemented in both the list and the detail view.

---

### 7. Seeders & Console Commands

**Seeders checked:**
- `DatabaseSeeder.php`, `EntitlementGradeSeeder.php`, `LeaveTypeSeeder.php`, `HolidaySeeder.php`, `WorkflowConfigurationSeeder.php`, `RoleAndPermissionSeeder.php`, `ArmantSchoolsSeeder.php`

None of these seed or initialize `leave_balances` rows.

**Console Commands:**
- `leave:notify-overdue` — notifies on overdue requests
- `GenerateArmantCredentialsPdf` — PDF export for schools

**There is NO command for:**
- Annual balance initialization
- Year-end carry-over
- New-year accrual

`carryOver()` and `accrueAnnual()` exist in `LeaveBalanceService` but are **never called** from any scheduled command, observer, event listener, or Filament action.

**Conclusion:** ❌ Missing: `leave:accrue-annual` and/or `leave:carry-over` Artisan commands. No scheduler setup for year-end processing.

---

### 8. Tests

**File:** `tests/Feature/Phase2ServicesTest.php`

Well-covered test cases confirmed:
- `getOrCreateBalance` sets grade-based entitlement (e.g. TEACHER_EXPERT → 40 days) ✅
- `deduct` / `refund` / `adjust` all tested with transaction assertions ✅
- `carryOver` moves remaining days to next year's `carried_over` ✅
- `accrueAnnual` sets correct `entitled` per grade (TEACHER_SENIOR → 45 days) ✅
- Full approval lifecycle (3 stages) correctly deducts balance ✅
- `SufficientBalanceRule` — warn-only mode and hard-fail mode ✅
- `CasualBeforeRegularRule`, `NoOverlapRule`, `MaxDaysPerRequestRule` ✅

**What is NOT tested:**
- That `carryOver` days do not roll forward a second time (expiry enforcement)
- That a UI action or scheduler actually triggers year-end logic

---

## What Is Implemented vs. What Is Missing

### ✅ Fully Implemented
1. `leave_balances` table with `year`, `entitled`, `carried_over`, `used`, unique key, and transaction ledger
2. `LeaveBalanceService` — deduct, refund, adjust, carry-over, annual accrual
3. Grade-based entitlement via `EntitlementGrade` model + `Employee::regularLeaveEntitlement()`
4. `deducts_balance` flag on `LeaveType`
5. Balance display in Filament: list column (badge) + view tab (table breakdown)
6. `carry_over_max_days` config key
7. Comprehensive unit/feature tests for all service methods

### ❌ Missing / Gaps

| Gap | Detail |
|---|---|
| **No year-end Artisan command** | `carryOver()` + `accrueAnnual()` exist in the service but are never triggered. Need a command `leave:year-end {year?}` that loops all active employees. |
| **No scheduler registration** | Even if the command were created, it needs to be registered in `routes/console.php` or a `Console/Kernel.php` schedule. |
| **No Filament year-end action** | Admins have no UI button to trigger year-end processing for an employee or all employees. |
| **No expiry enforcement for carried-over days** | The business rule "carried days expire at year-end and cannot be rolled forward again" is not enforced. The model has no `carry_over_expires_year` or `carry_over_source_year` column. If `carryOver` is run year after year, old carried-over days accumulate indefinitely. |
| **No balance initialization on employee creation** | When a new employee is added, `leave_balances` is empty until their first leave request is submitted (which calls `getOrCreateBalance`). Admins may see "—" in the balance badge. |

---

## Recommendations

### Priority 1 — Year-end Artisan Command (Required for Production)

Create `app/Console/Commands/AnnualLeaveRollover.php` with signature `leave:year-end {--year=} {--dry-run}`:

```
For each active Employee:
  1. $service->carryOver($emp, $currentYear - 1, $currentYear)
  2. $service->accrueAnnual($emp, $currentYear)
```

Register in `routes/console.php`:
```php
Schedule::command('leave:year-end')->yearlyOn(1, 1, '00:00');
```

### Priority 2 — Carry-over Expiry Enforcement

Add a `carry_over_source_year` column to `leave_balances` (nullable). When processing year N+1, zero out `carried_over` where `carry_over_source_year = N` (i.e., unused carry-over from year N cannot be re-carried to year N+2). This enforces the "can't use previous year's carried days in a new year" rule.

Alternatively (simpler): never run `carryOver` for a year that already has a `carried_over > 0` (idempotent guard). This prevents double carry-over but doesn't expire within-year remaining carry-over days.

### Priority 3 — Initialize Balance on Employee Creation (Nice to Have)

In `EmployeeResource::afterCreate()` or an `Employee` observer `created` hook, call `$service->accrueAnnual($employee, now()->year)` to pre-populate their balance immediately. Otherwise the balance badge shows "—" until their first request.

### Priority 4 — Filament Admin Action (Nice to Have)

Add a bulk action or a dedicated Filament page "تجديد الأرصدة السنوية" that calls the year-end command for selected employees or all employees in an organization.
