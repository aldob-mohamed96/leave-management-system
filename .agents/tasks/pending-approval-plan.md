# Implementation Plan — صفحة طلبات الاعتماد (PendingApprovalPage)

## Context discovered during exploration

- **Framework**: Laravel 11, Filament 3, PHP 8.2, Pest test runner (`php artisan test`)
- **Panel**: `AdminPanelProvider` uses `discoverPages()` — any class placed in `app/Filament/Pages/` is auto-registered. No manual registration needed.
- **Navigation group `'طلبات الإجازات'`** is already declared in `AdminPanelProvider::navigationGroups()`.
- **Stage-based query pattern**: `PendingMyApprovalWidget` (administration widget) shows the exact query idiom — `withoutGlobalScopes()`, filter by `organization_id` IN org subtree, `current_stage`, and `status` IN `[submitted, in_review]`.
- **Policy approve()**: school_principal stage ← `مدير مدرسة` in the same org; administration stages ← role check via `resolveStageRole()` mapping `leaves_officer→مسؤول الإجازات`, `hr_affairs→شؤون عاملين`, `admin_manager→مدير الإدارة`. The query must mirror this mapping so the page shows only requests the user can act on.
- **Service methods**: `LeaveRequestService::approve(request, step, user, note)`, `reject(request, step, user, reason)`, `returnRequest(request, step, user, note)` — identical signatures to those used in `LeaveRequestResource` table actions.
- **Blade view pattern**: every existing page view is wrapped in `<x-filament-panels::page>` with `dir="rtl"`. Views using `InteractsWithTable` emit `{{ $this->table }}` inside the wrapper.
- **Test command**: `php artisan test` (Pest, runs from project root).

---

## Plan

- [ ] 1. Create the Filament Page class `PendingApprovalPage`.

  Create `app/Filament/Pages/PendingApprovalPage.php` implementing `HasTable` + `InteractsWithTable`.

  **Navigation setup**:
  - `$navigationGroup = 'طلبات الإجازات'`
  - `$navigationLabel = 'طلبات الاعتماد'`
  - `$navigationIcon = 'heroicon-o-hand-raised'`
  - `$navigationSort = 2`
  - `$view = 'filament.pages.pending-approval-page'`
  - `getNavigationBadge()` — runs the same filtered query used in `getTableQuery()` and returns the count or null.

  **`canAccess()`**:
  ```php
  public static function canAccess(): bool
  {
      $user = Auth::user();
      $user?->setOrganizationTeam();
      return (bool) $user?->hasPermissionTo('approve_leave_request');
  }
  ```
  Also call `abort_unless(static::canAccess(), 403)` in `mount()`.

  **`getTableQuery()` — stage-based SQL filter (no per-record policy loop)**:

  Build the query as follows (mirroring `PendingMyApprovalWidget` and `LeaveRequestPolicy::approve()`):

  1. Start with `LeaveRequest::withoutGlobalScopes()->with(['employee', 'leaveType', 'organization'])`.
  2. Constrain status: `whereIn('status', [LeaveStatus::SUBMITTED->value, LeaveStatus::IN_REVIEW->value])`.
  3. Build a list of `current_stage` values the user is entitled to act on:
     - Call `$user->setOrganizationTeam()`.
     - If `$user->organization->isSchool()` and `$user->hasRole('مدير مدرسة')`: add `'school_principal'`, scope `organization_id = $user->organization_id`.
     - If `$user->organization->isAdministration()` or `$user->organization->isDirectorate()`:
       - `$user->hasRole('مسؤول الإجازات')` → add `'leaves_officer'`
       - `$user->hasRole('شؤون عاملين')` → add `'hr_affairs'`
       - `$user->hasRole('مدير الإدارة')` → add `'admin_manager'`
       - Scope `organization_id IN $user->organization->subtreeIds()`.
  4. If no stages were collected, return `$base->whereRaw('1 = 0')` (empty result, no crash).
  5. Apply `->whereIn('current_stage', $stages)` with the appropriate org scope.

  **`table(Table $table)`** — reuse the same column set as `LeaveRequestResource::table()` but limited to the columns relevant here:
  - `number` (searchable, sortable, copyable)
  - `employee.full_name` (searchable)
  - `organization.name`
  - `leaveType.name`
  - `start_date` (date `d F Y`, sortable)
  - `end_date` (date `d F Y`)
  - `days`
  - `status` badge with `displayStatusLabel()` / `displayStatusColor()`
  - `current_stage` formatted via `LeaveRequest::pendingApprovalLabel()`
  - `submitted_at` (datetime, sortable)

  Default sort: `submitted_at asc` (oldest pending first).

  **Three table actions** (copy the implementation verbatim from `LeaveRequestResource::table()` — the service call, error handling, and Notification patterns are identical):

  - **`approve`** — label `'اعتماد'`, icon `heroicon-o-check-circle`, color `success`.
    - Visible: `in_array($record->status, [SUBMITTED, IN_REVIEW]) && Auth::user()?->can('approve', $record)`.
    - Form: optional `Textarea::make('note')->label('ملاحظة (اختيارية)')->nullable()`.
    - Action: load pending step for `current_stage`, call `LeaveRequestService::approve(record, step, user, note)`.

  - **`reject`** — label `'رفض'`, icon `heroicon-o-x-circle`, color `danger`.
    - Visible: same status check + `can('reject', $record)`.
    - Form: required `Textarea::make('reason')->label('سبب الرفض')->required()`.
    - Action: load pending step, call `LeaveRequestService::reject(record, step, user, reason)`.

  - **`return`** — label `'إعادة للتعديل'`, icon `heroicon-o-arrow-uturn-left`, color `warning`.
    - Visible: same status check + `can('return', $record)`.
    - Form: required `Textarea::make('note')->label('الملاحظة')->required()`.
    - Action: load pending step, call `LeaveRequestService::returnRequest(record, step, user, note)`.

  All three actions wrap the service call in `try/catch (LeaveRequestException | ValidationException)` and use `Notification::make()->danger()->title($e->getMessage())->send()` on failure, matching the resource pattern exactly.

  Files: `app/Filament/Pages/PendingApprovalPage.php`

  Verify: `php artisan route:list --path=admin/pending 2>&1` should show the new page route. Also run `php artisan config:clear && php artisan view:clear` and navigate to the page in a browser (or run `php artisan test` to ensure no syntax errors break the autoloader).

---

- [ ] 2. Create the Blade view for the page.

  Create `resources/views/filament/pages/pending-approval-page.blade.php`.

  Structure (follow `reports-page.blade.php` pattern):
  ```blade
  <x-filament-panels::page>
      <div dir="rtl">
          {{ $this->table }}
      </div>
  </x-filament-panels::page>
  ```

  No extra components needed — `InteractsWithTable` renders the full table widget including filters, search, and pagination inside `{{ $this->table }}`.

  Files: `resources/views/filament/pages/pending-approval-page.blade.php`

  Verify: page renders without Blade compilation errors. Run `php artisan view:cache 2>&1` — should complete without errors.

---

- [ ] 3. Verify navigation badge wiring and page access.

  The `getNavigationBadge()` method should re-use the same query built in `getTableQuery()` (or a lightweight version of it) scoped to the current user. This must return `null` when the count is zero (to hide the badge) and the string count when > 0, matching the pattern in `LeaveRequestResource::getNavigationBadge()`.

  No new files — this is part of item 1. This item is a checklist reminder to confirm the badge is wired correctly.

  Verify: after logging in as a user with `approve_leave_request` permission and pending requests in their stage, the sidebar entry `طلبات الاعتماد` shows a numeric badge.

---

- [ ] 4. Write a Pest feature test for the page.

  Create `tests/Feature/PendingApprovalPageTest.php`.

  Cover:
  - A user without `approve_leave_request` permission gets 403 on `GET /admin/pending-approval`.
  - A user with the permission and the correct role sees only the requests matching their stage (not requests in other stages).
  - The approve action calls `LeaveRequestService::approve()` and returns a success notification.
  - The reject action requires a non-empty reason; empty reason yields a validation error.
  - The return action requires a note.

  Follow the Pest test style used in `tests/Feature/Phase2ServicesTest.php` (database transactions, `actingAs`, factories or manual model creation).

  Files: `tests/Feature/PendingApprovalPageTest.php`

  Verify: `php artisan test tests/Feature/PendingApprovalPageTest.php` — all new tests pass.

---

## Implementation notes for the coder

1. **OrganizationScope bypass**: always use `LeaveRequest::withoutGlobalScopes()` in `getTableQuery()`. The global `OrganizationScope` restricts to the auth user's org only, which would miss cross-org subtree queries for administration users. Mirror exactly what `PendingMyApprovalWidget` and `LeaveRequestResource::getEloquentQuery()` do.

2. **School-principal dual scope**: school principals see requests for `current_stage = 'school_principal'` scoped to `organization_id = $user->organization_id` only (not the subtree). Administration roles see their stage across the full subtree. Implement this with a `where` closure:
   ```php
   ->where(function ($q) use ($schoolOrgId, $adminStages, $adminOrgIds) {
       if ($schoolOrgId) {
           $q->orWhere(fn($s) => $s->where('current_stage', 'school_principal')
                                   ->where('organization_id', $schoolOrgId));
       }
       if (!empty($adminStages)) {
           $q->orWhere(fn($s) => $s->whereIn('current_stage', $adminStages)
                                   ->whereIn('organization_id', $adminOrgIds));
       }
   })
   ```

3. **`setOrganizationTeam()` call order**: must be called before any `hasRole()` / `hasPermissionTo()` check (Spatie teams). `canAccess()` calls it; `getTableQuery()` must call it again since it runs in a fresh request cycle.

4. **View file naming**: Filament 3 maps `$view = 'filament.pages.pending-approval-page'` to `resources/views/filament/pages/pending-approval-page.blade.php`. Match this exactly.

5. **No manual page registration needed**: `discoverPages()` in `AdminPanelProvider` auto-registers every class in `app/Filament/Pages/`. The new page will appear automatically.
