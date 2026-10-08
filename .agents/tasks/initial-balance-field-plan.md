# Implementation Plan: Initial Balance Field for Employee

## Investigation Findings

### 1. Execution order: Observer vs afterCreate

`EmployeeObserver::created()` fires **before** `afterCreate()` in `CreateEmployee`.

- Laravel dispatches model observers synchronously inside the `save()` call itself (specifically at the end of `Model::finishSave()`).
- Filament's `afterCreate()` is a hook called after `create()` returns — i.e., after the record is fully persisted and the observer has already run.
- **Conclusion:** when `afterCreate()` runs, the balance row for the regular leave type **already exists** (created by `EmployeeObserver::created()` → `accrueAnnual()`). There is no need to call `getOrCreateBalance()` again in `afterCreate()` — a direct `LeaveBalance::where(...)` lookup is safe.

### 2. `LeaveBalanceService::adjust()` — what it does to `carried_over`

`adjust()` does **not** touch `carried_over` at all. Its logic is:
- `$days > 0` → increments `entitled`
- `$days < 0` → decrements `used` (floor 0)

`carried_over` is only set by `carryOver()`.

**Decision:** Do not use `adjust()` for the initial-balance feature. Instead, directly increment `carried_over` on the balance row and write a `TransactionType::ADJUSTMENT` transaction manually. This keeps the audit trail intact and puts the legacy days in the semantically correct column (`carried_over` = days accumulated from a prior period, not new yearly entitlement).

### 3. TransactionType for the audit entry

Use `TransactionType::ADJUSTMENT` (`'adjustment'`). It is the correct constant for admin-initiated balance changes. `CARRYOVER` is reserved for year-end automated rollovers. The `LeaveBalanceTransaction` table accepts `note` (nullable string) — use it to record `"رصيد مبدئي قديم: X يوم"` so administrators can distinguish this from routine manual adjustments.

### 4. `initial_balance_days` storage decision

**Do not add a column to the `employees` table.** The value is a one-time seed; persisting it on the employee row would mislead future queries. Store it exclusively in `leave_balance_transactions` via the `note` field and let it flow into `leave_balances.carried_over`. The form field is `->dehydrated(false)` so it never gets written to `employees`.

### 5. Insertion point in `EmployeeResource::form()`

The `entitlement_grade` Select is the last field before the system-login Section. The initial balance field logically depends on the employee existing (it only matters at creation time), but must be visible on the create form. Place it directly after the `entitlement_grade` Select, before the `user_id` Select, and mark it `->visibleOn('create')` so it does not appear on the edit form (edit path uses `afterSave()` which reads from the live form data if present, but the field is semantically a "create-only seed").

Exact location in `EmployeeResource::form()`:
```
Forms\Components\Select::make('entitlement_grade')   ← existing, line ~95
    ...

// ← INSERT HERE
Forms\Components\TextInput::make('initial_balance_days')
    ...

Forms\Components\Select::make('user_id')             ← existing, continues after
```

### 6. EditEmployee / afterSave edge case

Because the field is `->visibleOn('create')`, it will not appear on the edit form. Therefore `afterSave()` in `EditEmployee` does not need to handle it. If the admin ever needs to adjust a balance on an existing employee, that is handled by the dedicated balance-adjustment UI. No changes are required to `EditEmployee.php`.

---

## Implementation Plan

- [ ] 1. Add a new migration to add `initial_balance_days` column... **wait — skip this**. Per finding §4, no DB column is added to `employees`. Migration not needed.

- [ ] 1. Add `initial_balance_days` field to `EmployeeResource::form()`.

  Add a `TextInput` directly after the `entitlement_grade` Select block and before the `user_id` Select block.

  **File:** `app/Filament/Resources/EmployeeResource.php`

  Insert after the closing `)` of the `entitlement_grade` Select:

  ```php
  Forms\Components\TextInput::make('initial_balance_days')
      ->label('رصيد إجازات اعتيادي سابق (أيام)')
      ->helperText('أدخل عدد الأيام التي يمتلكها الموظف من سنوات سابقة. سيُضاف إلى رصيد الترحيل للسنة الحالية.')
      ->numeric()
      ->integer()
      ->minValue(0)
      ->maxValue(365)
      ->nullable()
      ->default(null)
      ->dehydrated(false)
      ->visibleOn('create'),
  ```

  `->dehydrated(false)` ensures the value is never written to `employees`. `->visibleOn('create')` hides it on the edit form.

  **Verify:** Open the Create Employee page in the browser; the field appears between "الدرجة الوظيفية" and "ربط بحساب موجود". Open Edit Employee; the field is absent.

- [ ] 2. Read `initial_balance_days` in `mutateFormDataBeforeCreate()` and stash it, then strip it from `$data`.

  **File:** `app/Filament/Resources/EmployeeResource/Pages/CreateEmployee.php`

  Add a protected property:
  ```php
  protected int $initialBalanceDays = 0;
  ```

  In `mutateFormDataBeforeCreate()`, after the existing `unset()` block, add:
  ```php
  $this->initialBalanceDays = max(0, (int) ($this->data['initial_balance_days'] ?? $data['initial_balance_days'] ?? 0));
  unset($data['initial_balance_days']);
  ```

  The field is already `dehydrated(false)` so it will not appear in `$data` normally, but `$this->data` (the raw Livewire state) will contain it if the user filled it in. The `max(0, ...)` guards against negative input bypassing the form validator.

  **Verify:** Add a breakpoint / `dd($this->initialBalanceDays)` after the assignment and create a test employee with a value; confirm the integer is captured correctly.

- [ ] 3. Apply the initial balance in `afterCreate()` in `CreateEmployee`.

  **File:** `app/Filament/Resources/EmployeeResource/Pages/CreateEmployee.php`

  Add the necessary `use` statements at the top of the file:
  ```php
  use App\Enums\TransactionType;
  use App\Models\LeaveBalance;
  use App\Models\LeaveBalanceTransaction;
  use App\Models\LeaveType;
  use Illuminate\Support\Facades\DB;
  ```

  At the **end** of `afterCreate()` (after the `provisionSystemUser` block), add:

  ```php
  // Apply initial legacy balance days to carried_over
  if ($this->initialBalanceDays > 0) {
      $regularType = LeaveType::where('code', 'regular')->first();

      if ($regularType) {
          // The observer has already run accrueAnnual(), so the balance row exists.
          // Use firstOrCreate as a safety net in case the observer failed.
          $balance = LeaveBalance::firstOrCreate(
              [
                  'employee_id'   => $this->record->id,
                  'leave_type_id' => $regularType->id,
                  'year'          => now()->year,
              ],
              [
                  'entitled'     => $this->record->regularLeaveEntitlement(),
                  'carried_over' => 0,
                  'used'         => 0,
              ]
          );

          DB::transaction(function () use ($balance) {
              $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);

              LeaveBalanceTransaction::create([
                  'leave_balance_id' => $balance->id,
                  'type'             => TransactionType::ADJUSTMENT,
                  'days'             => $this->initialBalanceDays,
                  'note'             => "رصيد مبدئي قديم: {$this->initialBalanceDays} يوم",
                  'created_by'       => auth()->id(),
              ]);

              $balance->increment('carried_over', $this->initialBalanceDays);
          });
      }
  }
  ```

  **Why `firstOrCreate` instead of a bare `where()->first()`:** `EmployeeObserver::created()` wraps `accrueAnnual()` in a try/catch that swallows exceptions. If the observer failed silently (e.g., no active `LeaveType` with `yearly_entitlement > 0`), the row would not exist. `firstOrCreate` is the safe fallback.

  **Why `carried_over` and not `entitled`:** The incoming days are a legacy balance from prior years, not new annual entitlement for the current year. `carried_over` is the semantically correct column (it is added to `remaining` calculation as `entitled + carried_over - used`).

  **Verify:**
  ```bash
  php artisan tinker
  # Create an employee with initial_balance_days = 10 via the UI, then:
  App\Models\LeaveBalance::where('employee_id', <id>)->get(['employee_id','year','entitled','carried_over','used'])
  # carried_over should equal 10
  App\Models\LeaveBalanceTransaction::whereHas('leaveBalance', fn($q)=>$q->where('employee_id', <id>))
      ->get(['type','days','note'])
  # type = 'adjustment', days = 10, note = 'رصيد مبدئي قديم: 10 يوم'
  ```

- [ ] 4. (No changes to EditEmployee) Confirm `EditEmployee.php` requires no modifications.

  The field is hidden on edit via `->visibleOn('create')`. `afterSave()` in `EditEmployee` does not need to handle `initial_balance_days`. If the admin needs to correct a balance on an existing employee, the existing balance-management UI (or future adjustment action) handles that.

  **Verify:** Open an existing employee's edit page; confirm "رصيد إجازات اعتيادي سابق" field is not present.

---

## Edge Cases

| Case | Behaviour |
|------|-----------|
| Field left blank / null | `$this->initialBalanceDays` = 0; the `if ($this->initialBalanceDays > 0)` guard skips the entire block. No transaction written. |
| User enters 0 explicitly | Same as blank — guard skips. |
| `LeaveType` code=`regular` does not exist in DB | `$regularType` is null; the `if ($regularType)` guard skips. Log warning is not explicitly added here but the observer's own log covers the absence. Consider adding `Log::warning(...)` for robustness. |
| Observer failed silently and no balance row yet | `firstOrCreate` creates the row with `entitled` from `regularLeaveEntitlement()` and `carried_over=0`, then increments `carried_over`. Correct. |
| `initial_balance_days` bypasses HTML min/max | `max(0, (int) ...)` in `mutateFormDataBeforeCreate` floors at 0. The `->maxValue(365)` validation on the form provides the upper bound; no DB constraint needed since it goes into `carried_over` which is `decimal(5,1)` (max 9999.9). |
| Duplicate employee creation (double-submit) | The `leave_balances` unique constraint on `(employee_id, leave_type_id, year)` prevents duplicate rows. `firstOrCreate` is idempotent. The transaction ledger may get a second entry if `afterCreate` fires twice, but double-submit in Filament is blocked by the form's submit button being disabled on first click. |

---

## Files Modified

| File | Change |
|------|--------|
| `app/Filament/Resources/EmployeeResource.php` | Add `initial_balance_days` TextInput after `entitlement_grade` Select |
| `app/Filament/Resources/EmployeeResource/Pages/CreateEmployee.php` | Add `$initialBalanceDays` property; populate in `mutateFormDataBeforeCreate()`; apply balance in `afterCreate()` |

## Files NOT Modified

| File | Reason |
|------|--------|
| `app/Filament/Resources/EmployeeResource/Pages/EditEmployee.php` | Field is create-only; no balance logic needed |
| `app/Services/LeaveBalanceService.php` | `adjust()` does not touch `carried_over`; we bypass it with direct DB write + manual transaction entry |
| `app/Models/Employee.php` | No column added; `initial_balance_days` is ephemeral form data only |
| `app/Observers/EmployeeObserver.php` | Observer fires first, creates the balance row; no changes needed |
| `database/migrations/` | No schema change; `carried_over` column already exists |
