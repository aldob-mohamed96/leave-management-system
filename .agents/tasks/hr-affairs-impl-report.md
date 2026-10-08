# Implementation Report — hr_affairs Approval Stage

## Summary

All six change groups from the plan have been implemented and verified.

## Changes Applied

| File | Change |
|---|---|
| `database/seeders/WorkflowConfigurationSeeder.php` | Added `hr_affairs` stage at `step_order=3`, bumped `admin_manager` to `step_order=4`, updated info message |
| `database/seeders/RoleAndPermissionSeeder.php` | Added `hr_affairs` role (`شؤون عاملين`) for Administration orgs with 8 permissions |
| `app/Models/LeaveRequest.php` | Added `'hr_affairs' => 'شؤون عاملين'` to `stageLabel()` match |
| `app/Services/LeaveRequestPdfService.php` | Added `hr_affairs => 'شؤون عاملين'` row in `buildApprovalRows()`; separated `leaves_officer` label from the old combined string |
| `app/Policies/LeaveRequestPolicy.php` | Added `hr_affairs` to `approve`, `reject`, `return` stage arrays; added `resolveStageRole()` helper; applied role-based gate check in all three methods |
| `app/Filament/Resources/LeaveRequestResource/Pages/ViewLeaveRequest.php` | Added `->visible(fn(): bool => $this->getRecord()->status === LeaveStatus::APPROVED)` to `print` and `downloadPdf` actions |

## Verification Results

| Step | Result |
|---|---|
| `php artisan db:seed --class=WorkflowConfigurationSeeder` | ✓ Exit 0 — 69 schools updated (4-stage chain confirmed) |
| `php artisan db:seed --class=RoleAndPermissionSeeder` | ✓ Exit 0 — Roles created for 72 organizations |
| `php artisan route:cache` | ✓ Exit 0 — No class-loading errors |
| `php artisan config:cache` | ✓ Exit 0 — Configuration cached successfully |

## Workflow After Change

| # | stage_name | Arabic label | role |
|---|---|---|---|
| 1 | `school_principal` | مدير المدرسة | مدير مدرسة |
| 2 | `leaves_officer` | مسؤول الإجازات | مسؤول الإجازات |
| 3 | `hr_affairs` | شؤون عاملين | شؤون عاملين |
| 4 | `admin_manager` | مدير الإدارة | مدير الإدارة |

Print and PDF download buttons are now hidden unless the request status is `APPROVED`.
