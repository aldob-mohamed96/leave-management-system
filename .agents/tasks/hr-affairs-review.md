# hr_affairs Approval Stage

The change inserts `شؤون عاملين` (hr_affairs) as step 3 in the leave-request approval chain, pushing `admin_manager` to step 4. All six touch-points — workflow seeder, role seeder, model label, PDF rows, policy gate, and the print/download visibility guard — are updated consistently. The implementation report claims all seeders ran at exit 0 against the live database.

**Watch for:** The `resolveStageRole` helper returns `null` for any unknown stage, which makes the role gate fail-open for unrecognised stages — confirmed. A future stage added to the seeder without a matching case would silently allow any administration user to act on it.

**Verdict**: APPROVED

---

## High-level view

The four-stage chain is defined in one place (`SCHOOL_STAGES`) and the seeder uses `updateOrCreate` keyed on `(organization_id, step_order)`. Because step_order 3 previously belonged to `admin_manager`, re-seeding updates that row to `hr_affairs` and inserts a new row at step_order 4. Old rows with `step_order > 4` are pruned and the legacy `direct_manager` stage is deleted.

The `resolveStageRole` helper introduced in the policy centralises stage-to-role mapping for `approve`, `reject`, and `return`. The match expression covers all three administration stages; for any unknown stage it returns `null`, which makes the role check `true` unconditionally — a fail-open default. A future stage added to the seeder without a corresponding case here would silently grant any administration user access to it.

The `hr_affairs` role is scoped to `ADMINISTRATION` orgs only, with a permission set that mirrors `leaves_officer` exactly.

PDF and print buttons are gated on `status === APPROVED`. The service-layer `guardStepAction` independently validates request state, so the UI guard is the only change here.

---

<details>
<summary>Issues (1)</summary>

1. **Fail-open resolveStageRole** — `resolveStageRole` returns `null` for unrecognised stages, making the role check pass unconditionally for any future stage not listed in the match. Add `default => throw new \InvalidArgumentException("No role defined for stage: $stage")` to make the gate fail-closed. As written, a newly added seeder stage without a matching case silently allows any administration user to act on it.

</details>

---

<details>
<summary>Details</summary>

### Role gate and the fail-open default

`resolveStageRole` maps stage strings to Arabic role names:

```php
return match ($stage) {
    'leaves_officer' => 'مسؤول الإجازات',
    'hr_affairs'     => 'شؤون عاملين',
    'admin_manager'  => 'مدير الإدارة',
    default          => null,
};
```

The `null` return is consumed as:

```php
return $requiredRole === null || $user->hasRole($requiredRole);
```

Any stage not in the match evaluates `null === null` → `true`, granting access to any administration user. The three named stages are correct for the current seeder, so this is not a live bug today. It becomes one the moment a new stage is added to the seeder without updating this method.

</details>

---

<details>
<summary>File map</summary>

| File | What changed |
|---|---|
| `database/seeders/WorkflowConfigurationSeeder.php` | Added `hr_affairs` at step_order 3; `admin_manager` bumped to step_order 4 |
| `database/seeders/RoleAndPermissionSeeder.php` | Added `hr_affairs` role for ADMINISTRATION orgs with 8 permissions |
| `app/Models/LeaveRequest.php` | Added `'hr_affairs' => 'شؤون عاملين'` case to `stageLabel()` |
| `app/Services/LeaveRequestPdfService.php` | Split old combined label; added `hr_affairs` row in correct position |
| `app/Policies/LeaveRequestPolicy.php` | Added `hr_affairs` to all three stage arrays; extracted `resolveStageRole()` helper |
| `app/Filament/Resources/LeaveRequestResource/Pages/ViewLeaveRequest.php` | Added `APPROVED`-only visibility guard to print and downloadPdf actions |

Full diff: `git diff HEAD~1 HEAD`

</details>
