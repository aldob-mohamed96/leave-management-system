# Approval Role Investigation Report

## Summary Answer

**Recommended approach: add an `approval_role` enum column to the `employees` table.**

The existing system uses Spatie roles on the *User* (system account) to control who can approve workflow steps. However, employees are a separate entity — many employees don't have a system `user_id` at all. The user request asks: *"if the employee is from a school, add an approval flag (school principal); if they are an administration employee, add one of three approval roles (leaves specialist / HR officer / admin manager)."*

Adding `approval_role` to `employees` directly models this business rule at the right layer: it describes the employee's organizational seniority/role, not their system access level. This mirrors how `job_title` and `grade` already work on the employee record.

---

## Evidence

### 1. How the Approval Workflow is Modeled

**File: `app/Models/LeaveRequest.php`**
- `current_stage` (string): tracks the active approval stage name (`school_principal`, `leaves_officer`, `hr_affairs`, `admin_manager`).
- `status` (enum `LeaveStatus`): overall request status.
- Relationship `steps()` → `LeaveRequestStep` ordered by `step_order`.

**File: `app/Models/LeaveRequestStep.php`**
- Columns: `leave_request_id`, `step_order`, `stage` (string), `status` (`StepStatus` enum), `acted_by`, `acted_at`, `note`.
- `stage` is a free-text string matching `workflow_configurations.stage_name`.
- No per-step "assigned approver" — steps are assigned by Spatie role, not by a specific user or employee record.

**File: `app/Enums/StepStatus.php`**
- Values: `pending`, `approved`, `rejected`, `returned`, `skipped`.

**File: `app/Enums/ApprovalRule.php`**
- Values: `all`, `any`, `majority` — governs how many approvers are needed to advance a stage.

---

### 2. How Approval Steps are Defined

**File: `app/Models/WorkflowConfiguration.php`** / **migration `2026_10_05_000006_create_workflow_configurations_table.php`**

```
workflow_configurations
  id
  organization_id   (FK → organizations)
  stage_name        string(50)   e.g. 'school_principal', 'leaves_officer', 'hr_affairs', 'admin_manager'
  step_order        tinyint unsigned
  approval_rule     string(20)   'all' | 'any' | 'majority'
  required_role     string(100)  nullable — Spatie role name e.g. 'مسؤول الإجازات'
  label             string
  is_active         boolean
```

- Steps are tied to **Spatie role names** (`required_role`), not to employee records.
- `unique(['organization_id', 'step_order'])` — one step per order slot per org.

**File: `database/seeders/WorkflowConfigurationSeeder.php`**

School workflow (4 stages in order):

| step_order | stage_name       | required_role    |
|-----------|------------------|------------------|
| 1         | school_principal | مدير مدرسة       |
| 2         | leaves_officer   | مسؤول الإجازات   |
| 3         | hr_affairs       | شؤون عاملين      |
| 4         | admin_manager    | مدير الإدارة     |

---

### 3. Employee Model — No Approval Columns

**File: `app/Models/Employee.php`** / **migration `2026_10_05_000002_create_employees_table.php`**

Current employee columns:
```
id, organization_id, user_id (nullable FK → users),
employee_code, full_name, job_title, grade, entitlement_grade,
birth_date, hire_date, work_start_date, phone, is_active
```

- **No approval-related column** currently exists.
- `user_id` is nullable — employees don't need a system account.
- `job_title` and `grade` are free-text, not tied to workflow logic.

---

### 4. How LeaveRequestService Advances Workflow Steps

**File: `app/Services/LeaveRequestService.php`**

Key observations in `submit()`:
```php
// Builds steps from WorkflowConfiguration, ordered by step_order
$stages = WorkflowConfiguration::withoutGlobalScopes()
    ->where('organization_id', $request->organization_id)
    ->where('is_active', true)
    ->orderBy('step_order')
    ->get();
```

Key observations in `approve()`:
```php
// Gets approval rule from WorkflowConfiguration
$wfConfig = WorkflowConfiguration::where('organization_id', $request->organization_id)
    ->where('stage_name', $step->stage)
    ->where('is_active', true)
    ->first();

// Advances to next stage by step_order
$nextStage = WorkflowConfiguration::where('organization_id', $request->organization_id)
    ->where('is_active', true)
    ->where('step_order', '>', $wfConfig?->step_order ?? $step->step_order)
    ->orderBy('step_order')
    ->first();
```

Key observation in `resolveSchoolPrincipalApprover()`:
```php
// Finds the school principal approver by Spatie role name 'مدير مدرسة'
$manager = User::query()
    ->where('organization_id', $organizationId)
    ->where('is_active', true)
    ->whereHas('roles', function ($query) use ($organizationId) {
        $query->where('name', 'مدير مدرسة')->where('roles.organization_id', $organizationId);
    })
    ->first();
```

Key observation in `getUsersForStage()` (notifications):
```php
// Looks up $wfConfig->required_role and filters User by Spatie role
$users = User::where('organization_id', $request->organization_id)
    ->where('is_active', true)
    ->get()
    ->filter(fn($u) => $u->hasRole($wfConfig->required_role));
```

**Conclusion:** the service finds approvers by checking Spatie roles on User, not by looking at the Employee record. The Employee's `approval_role` column would be a separate, parallel concept — used to determine *what workflow stages/paths* apply to the employee when they submit a request.

---

### 5. Roles in the System

**File: `database/seeders/RoleAndPermissionSeeder.php`**

Roles per organization type:

**School:**
- `موظف مدرسة` (school_employee)
- `مدير مدرسة` (school_manager) — has `approve_leave_request`
- `وكيل مدرسة` (school_assistant)
- `أخصائي` (school_specialist)

**Administration:**
- `مسؤول الإجازات` (leaves_officer) — has `approve_leave_request`
- `شؤون عاملين` (hr_affairs) — has `approve_leave_request`
- `مدير الإدارة` (admin_manager) — has `approve_leave_request`
- `كاتب الإدارة` (admin_clerk)

**Directorate:**
- `مدير المديرية`, `وكيل المديرية`, `منسق`

Roles are Spatie team-scoped by `organization_id`. The User model uses `HasRoles` and `setOrganizationTeam()` to scope checks.

---

### 6. User → Employee Relationship

**File: `app/Models/User.php`**
```php
public function employee(): HasOne
{
    return $this->hasOne(Employee::class);
}
```

**File: `app/Models/Employee.php`**
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

- One-to-one: a User optionally has one Employee; an Employee optionally links to one User.
- Many employees have no system user account (`user_id` is nullable).
- The Spatie role is on the User, not the Employee.

---

### 7. EmployeeResource — Current Form Fields

**File: `app/Filament/Resources/EmployeeResource.php`**

Current create/edit form fields:
1. `employee_code` — required, unique
2. `full_name` — required
3. `job_title` — optional text
4. `organization_id` — Select (hidden for school actors)
5. `entitlement_grade` — Select (from EntitlementGrade table)
6. `user_id` — Select (link to existing User; hidden if creating system account)
7. **Section: "حساب دخول للنظام"** — toggle + email/phone/password for new user creation
8. `birth_date`, `hire_date`, `work_start_date` — date pickers
9. `phone` — tel input
10. `is_active` — toggle

No approval-role field exists yet.

---

## Conclusions and Recommendations

### The Business Rule (from user messages)
- **School employee** → one approval level: `school_principal` (مدير المدرسة)
- **Administration employee** → three approval levels, one of:
  1. `leaves_officer` (مسؤول الإجازات)
  2. `hr_affairs` (شؤون عاملين)
  3. `admin_manager` (مدير الإدارة)

This is about *who the employee is* in the organizational hierarchy, not *what system access they have*.

---

### Recommended Approach: `approval_role` enum column on `employees`

**Why not Spatie roles on User?**
- Spatie roles on User already control who can *act as approver* (approve/reject steps). Reusing them to describe an employee's *position in the approval chain* mixes two distinct concerns.
- Many employees have no `user_id` — they cannot have Spatie roles at all.
- Adding another Spatie role per employee would require every employee to have a User account, which conflicts with the current optional design.

**Why the enum column is the right fit:**
- Mirrors existing patterns: `job_title` and `entitlement_grade` are already employee-level attributes.
- No new tables needed — a simple migration adds one nullable column.
- Clean for the EmployeeResource form: a single Select field with 4 options, shown conditionally based on organization type.
- Easy to query: `Employee::where('approval_role', 'admin_manager')` to find all admin managers.

**Proposed enum values:**
```php
enum ApprovalRole: string
{
    case SCHOOL_PRINCIPAL = 'school_principal';  // مدير المدرسة
    case LEAVES_OFFICER   = 'leaves_officer';    // مسؤول الإجازات
    case HR_AFFAIRS       = 'hr_affairs';        // شؤون عاملين
    case ADMIN_MANAGER    = 'admin_manager';     // مدير الإدارة
}
```

**Implementation steps needed:**

1. **Create `app/Enums/ApprovalRole.php`** — PHP enum with the 4 values above, plus `label()` returning Arabic names.

2. **Migration** — add nullable `approval_role` string column to `employees` table:
   ```php
   $table->string('approval_role', 30)->nullable()->after('job_title');
   ```

3. **Update `app/Models/Employee.php`**:
   - Add `'approval_role'` to `$fillable`.
   - Add `'approval_role' => ApprovalRole::class` to `$casts`.

4. **Update `app/Filament/Resources/EmployeeResource.php`** form:
   - Add a `Select::make('approval_role')` with options from `ApprovalRole::class`.
   - Show only for administration employees OR show always with a label explaining it's for administration approval chain.
   - Label: `دور الاعتماد`.

5. **No changes needed to `LeaveRequestService`** — the approval workflow routing is already driven by `WorkflowConfiguration` and Spatie roles on User. The `approval_role` on Employee is purely informational/organizational metadata (and could later be used to auto-assign the user's Spatie role on provisioning).

**Optional follow-up:** When `EmployeeResource::provisionSystemUser()` creates a system user, it could auto-assign the corresponding Spatie role based on `$employee->approval_role`. Currently it always assigns `موظف مدرسة`. For admin employees with `approval_role` set, it could assign `مسؤول الإجازات`, `شؤون عاملين`, or `مدير الإدارة` instead.

---

### What NOT to Do

- **Do not** create a separate `employee_approval_roles` pivot table — the relationship is one-to-one (one approval role per employee).
- **Do not** add a new Spatie role for this — existing roles already cover the approver side.
- **Do not** use a polymorphic approach — the enum column is simpler and sufficient.
