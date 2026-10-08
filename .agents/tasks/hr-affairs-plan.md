# Implementation Plan — Add `hr_affairs` Approval Stage

## What is changing

The leave-request workflow is growing from 3 stages to 4:

| # | stage_name | Arabic label | role |
|---|---|---|---|
| 1 | `school_principal` | مدير المدرسة | مدير مدرسة |
| 2 | `leaves_officer` | مسؤول الإجازات | مسؤول الإجازات |
| **3** | **`hr_affairs`** | **شؤون عاملين** | **شؤون عاملين** |
| 4 | `admin_manager` | مدير الإدارة | مدير الإدارة |

`LeaveRequestService` is fully workflow-driven (reads `WorkflowConfiguration` at submit time and advances stage by `step_order`), so no logic changes are needed there. The changes fall into five groups:

1. Seeder — add the new stage and renumber `admin_manager` to step 4.
2. Role seeder — create the `شؤون عاملين` role for Administration orgs.
3. Policy — allow the new stage in the `approve`, `reject`, and `return` guards.
4. Model `stageLabel` — add an Arabic label for `hr_affairs`.
5. PDF service `buildApprovalRows` + both Blade templates — add the new approval row.

---

## Items

- [ ] 1. **Add `hr_affairs` stage to `WorkflowConfigurationSeeder`**

  Insert a new entry at `step_order = 3` and change `admin_manager` to `step_order = 4`.
  The seeder's cleanup loop already deletes orphan steps with `step_order > count(SCHOOL_STAGES)`,
  so bumping the constant to 4 entries handles the pruning automatically.

  **File:** `database/seeders/WorkflowConfigurationSeeder.php`

  Replace the `SCHOOL_STAGES` constant from:

  ```php
  private const SCHOOL_STAGES = [
      [
          'stage_name'    => 'school_principal',
          'step_order'    => 1,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مدير مدرسة',
          'label'         => 'اعتماد مدير المدرسة',
      ],
      [
          'stage_name'    => 'leaves_officer',
          'step_order'    => 2,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مسؤول الإجازات',
          'label'         => 'رأي مسؤول الإجازات',
      ],
      [
          'stage_name'    => 'admin_manager',
          'step_order'    => 3,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مدير الإدارة',
          'label'         => 'رأي مدير الإدارة',
      ],
  ];
  ```

  To:

  ```php
  private const SCHOOL_STAGES = [
      [
          'stage_name'    => 'school_principal',
          'step_order'    => 1,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مدير مدرسة',
          'label'         => 'اعتماد مدير المدرسة',
      ],
      [
          'stage_name'    => 'leaves_officer',
          'step_order'    => 2,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مسؤول الإجازات',
          'label'         => 'رأي مسؤول الإجازات',
      ],
      [
          'stage_name'    => 'hr_affairs',
          'step_order'    => 3,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'شؤون عاملين',
          'label'         => 'رأي شؤون عاملين',
      ],
      [
          'stage_name'    => 'admin_manager',
          'step_order'    => 4,
          'approval_rule' => ApprovalRule::ANY,
          'required_role' => 'مدير الإدارة',
          'label'         => 'رأي مدير الإدارة',
      ],
  ];
  ```

  Also update the info string at the bottom of `run()`:

  ```php
  // old
  $this->command->info("✓ School workflow configured for {$schools->count()} schools (principal → leaves officer → admin manager).");
  // new
  $this->command->info("✓ School workflow configured for {$schools->count()} schools (principal → leaves officer → hr affairs → admin manager).");
  ```

  **Verify:** Run `php artisan db:seed --class=WorkflowConfigurationSeeder` against a fresh/test database and confirm 4 `workflow_configurations` rows exist per school with `step_order` 1–4 and `stage_name` values `school_principal`, `leaves_officer`, `hr_affairs`, `admin_manager`.

---

- [ ] 2. **Add the `شؤون عاملين` role in `RoleAndPermissionSeeder`**

  The new role needs `approve_leave_request`, `reject_leave_request`, `return_leave_request`, and read permissions — mirroring the `leaves_officer` role exactly. Add a new entry in the `ROLES` constant inside `RoleAndPermissionSeeder`:

  **File:** `database/seeders/RoleAndPermissionSeeder.php`

  After the `leaves_officer` block (around line 133), add:

  ```php
  'hr_affairs' => [
      'label'       => 'شؤون عاملين',
      'org_types'   => [OrganizationType::ADMINISTRATION],
      'permissions' => [
          'approve_leave_request',
          'reject_leave_request',
          'return_leave_request',
          'view_leave_requests',
          'view_all_leave_requests',
          'view_employees',
          'view_reports',
          'export_reports',
      ],
  ],
  ```

  No changes to the `PERMISSIONS` array are required — all needed permissions already exist.

  **Verify:** Run `php artisan db:seed --class=RoleAndPermissionSeeder` and confirm a role named `شؤون عاملين` exists for each Administration organization, with the 8 permissions listed.

---

- [ ] 3. **Update `LeaveRequestPolicy` to include `hr_affairs` in administration stage checks**

  Three methods hard-code `['leaves_officer', 'admin_manager']` as the allowed stages for administration actors: `approve`, `reject`, and `return`. Add `hr_affairs` to all three arrays.

  **File:** `app/Policies/LeaveRequestPolicy.php`

  Change every occurrence of:
  ```php
  && in_array($leaveRequest->current_stage, ['leaves_officer', 'admin_manager'], true);
  ```
  To:
  ```php
  && in_array($leaveRequest->current_stage, ['leaves_officer', 'hr_affairs', 'admin_manager'], true);
  ```

  This affects **three lines** (inside `approve`, `reject`, `return` — confirmed at lines 109, 122, 141 in the source as read).

  **Verify:** `php artisan test --filter=LeaveRequestPolicyTest` (or run the full feature suite — see item 7).

---

- [ ] 4. **Update `LeaveRequest::stageLabel()` to return an Arabic label for `hr_affairs`**

  **File:** `app/Models/LeaveRequest.php`

  Change the `stageLabel` match from:
  ```php
  return match ($stage) {
      'direct_manager', 'school_principal' => 'مدير المدرسة',
      'leaves_officer' => 'مسؤول الإجازات',
      'admin_manager' => 'مدير الإدارة',
      default => $stage ? str_replace('_', ' ', $stage) : '—',
  };
  ```
  To:
  ```php
  return match ($stage) {
      'direct_manager', 'school_principal' => 'مدير المدرسة',
      'leaves_officer' => 'مسؤول الإجازات',
      'hr_affairs'     => 'شؤون عاملين',
      'admin_manager'  => 'مدير الإدارة',
      default => $stage ? str_replace('_', ' ', $stage) : '—',
  };
  ```

  `stageLabel` is called from:
  - `LeaveRequestResource.php` table column and infolist `current_stage` entry
  - `LeaveRequestResource.php` infolist steps repeater `stage` entry
  - `PendingRequestsWidget.php` table column

  All of these will render `شؤون عاملين` automatically after this one change.

  **Verify:** Unit-test `LeaveRequest::stageLabel('hr_affairs')` returns `'شؤون عاملين'`. This is covered by item 7's test additions.

---

- [ ] 5. **Update `LeaveRequestPdfService::buildApprovalRows()` to include the `hr_affairs` row**

  The `$defaults` array in `buildApprovalRows` hard-codes the three stage keys. Add `hr_affairs` between `leaves_officer` and `admin_manager`.

  **File:** `app/Services/LeaveRequestPdfService.php`

  Change:
  ```php
  $defaults = [
      'school_principal' => 'مدير المدرسة (توقيع إلكتروني)',
      'leaves_officer'   => 'مسؤول الإجازات / شؤون العاملين',
      'admin_manager'    => 'مدير الإدارة',
  ];
  ```
  To:
  ```php
  $defaults = [
      'school_principal' => 'مدير المدرسة (توقيع إلكتروني)',
      'leaves_officer'   => 'مسؤول الإجازات',
      'hr_affairs'       => 'شؤون عاملين',
      'admin_manager'    => 'مدير الإدارة',
  ];
  ```

  Note: the old label `'مسؤول الإجازات / شؤون العاملين'` was a combined placeholder because there was no dedicated stage. Now that `hr_affairs` is its own row, the `leaves_officer` label reverts to `'مسؤول الإجازات'` alone.

  The `approvalRows` array is passed directly to both Blade templates via `viewData()`, so no Blade changes are needed for the approval table — it iterates `$approvalRows` dynamically with `@foreach`.

  **Verify:** Generate a PDF for a test request that has gone through all 4 stages and confirm the approval table contains 4 rows: مدير المدرسة, مسؤول الإجازات, شؤون عاملين, مدير الإدارة.

---

- [ ] 6. **Add a migration to widen the `current_stage` column comment (optional but recommended)**

  The migration `2026_10_05_000005_create_leave_requests_tables.php` has an inline comment listing allowed stage values. This is cosmetic only — the column is `varchar(30)` and `hr_affairs` (9 chars) fits. No structural migration is required.

  **If the team maintains live annotation comments**, create a new migration:

  ```
  database/migrations/YYYY_MM_DD_000001_annotate_hr_affairs_stage.php
  ```

  ```php
  public function up(): void
  {
      // No structural change — hr_affairs fits within varchar(30).
      // This migration only serves as a changelog marker.
  }
  ```

  **Decision:** Skip this migration unless the team convention requires it. No schema change is needed.

---

- [ ] 7. **Update the feature tests to reflect the 4-stage workflow**

  Two test files reference the old 3-stage path and will fail after the seeder change:

  **File:** `tests/Feature/Phase2ServicesTest.php`

  - The "full happy path" test approves 3 stages (`direct_manager → leaves_officer → admin_manager`). In that test's setup, the `WorkflowConfigurationFactory` is used directly, so add an `hr_affairs` stage step between `leaves_officer` and `admin_manager` in that test's context factory setup.
  - The test that checks `current_stage` becomes `leaves_officer` after approving `direct_manager` is for a legacy 3-stage fixture and does not need to change (it uses `WorkflowConfigurationFactory` not the real seeder).
  - The test "approve all 3 stages" iterates `['direct_manager', 'leaves_officer', 'admin_manager']` — if this test uses the seeder-based workflow (4 stages), update the array to `['direct_manager', 'leaves_officer', 'hr_affairs', 'admin_manager']` and the expected step count from 3 to 4.

  Read the test context setup (`setupPhase2`) at the top of `Phase2ServicesTest.php` to determine whether it uses the factory directly or the seeder, then update accordingly. (The plan author observed that `setupPhase2` uses `WorkflowConfigurationFactory` with explicit stages, not the seeder, so only tests that explicitly list `admin_manager` as the "last" stage need updating.)

  **File:** `tests/Feature/Phase1FoundationTest.php`

  - The test around line 552 checks `count = 3` stages and lists `direct_manager, leaves_officer, admin_manager`. This is a factory-based fixture test. If it verifies the 3-stage count and the seeder is not involved, no change is needed. If it calls the seeder, update to 4.

  **Verify:** `php artisan test tests/Feature/Phase2ServicesTest.php tests/Feature/Phase1FoundationTest.php`

---

- [ ] 8. **Re-seed the database and smoke-test end-to-end**

  After all code changes:

  1. Run `php artisan db:seed --class=RoleAndPermissionSeeder` to create the `شؤون عاملين` role for all existing Administration orgs.
  2. Run `php artisan db:seed --class=WorkflowConfigurationSeeder` to update all existing school workflows to 4 stages.
  3. Assign the `شؤون عاملين` role to at least one test user in an Administration org.
  4. Submit a new leave request and confirm the approval flow advances through all 4 stages in order.
  5. Confirm the PDF/print shows 4 approval rows.
  6. Confirm a request does **not** show the Print/Download PDF button until all 4 stages are approved (`status = APPROVED`) — this is enforced by the existing `ViewLeaveRequest.php` URL action which is always visible but the PDF itself shows "pending" state; the print button is not gated by approval status in the current UI. No change needed there.

  **Verify:** `php artisan test` — full suite green.

---

## Summary of files changed

| File | What changes |
|---|---|
| `database/seeders/WorkflowConfigurationSeeder.php` | Add `hr_affairs` at step 3, bump `admin_manager` to step 4 |
| `database/seeders/RoleAndPermissionSeeder.php` | Add `شؤون عاملين` role for Administration orgs |
| `app/Policies/LeaveRequestPolicy.php` | Add `hr_affairs` to the 3 stage-array guards |
| `app/Models/LeaveRequest.php` | Add `hr_affairs => شؤون عاملين` to `stageLabel()` match |
| `app/Services/LeaveRequestPdfService.php` | Add `hr_affairs` row in `buildApprovalRows()` |
| `tests/Feature/Phase2ServicesTest.php` | Update any 3-stage assertions that go through all stages |
| `tests/Feature/Phase1FoundationTest.php` | Review only; update if seeder-based |

`LeaveRequestService.php` requires **no changes** — stage advancement is fully driven by `WorkflowConfiguration.step_order`.

`ViewLeaveRequest.php` requires **no changes** — actions work on `current_stage` generically.

`LeaveRequestResource.php` requires **no changes** — table/infolist columns call `stageLabel()` which is updated in item 4.

Both Blade templates require **no changes** — they iterate `$approvalRows` dynamically.
