# Implementation Plan — Phase 3: Filament Admin Panel

> Project: `/Users/mohamedgaber/projects/vaccancy/leave-management-system`
> Stack: Laravel 11 · PHP 8.3 · Filament 3.x · Spatie Permission (teams) · Pest 3
> This plan is decomposed into three FEAT artifacts under `.agents/tasks/phase3-services/features/`.
> The workflow executes them sequentially: FEAT-001 → FEAT-002 → FEAT-003.

---

## FEAT-001 — Panel Bootstrap, FilamentUser & Policies

- [ ] 1. **Modify `app/Providers/Filament/AdminPanelProvider.php`**
      Change `Color::Amber` → `Color::Blue` for primary color.
      Add `->locale('ar')`, `->brandName('نظام إدارة الإجازات — مديرية الأقصر')`,
      `->font('Tajawal', provider: GoogleFontProvider::class)`.
      Add four `NavigationGroup` entries (المؤسسات والمستخدمون, الموظفون, طلبات الإجازات, الإعدادات).
      Remove `FilamentInfoWidget` from the widgets list.
      Files: `app/Providers/Filament/AdminPanelProvider.php`
      Verify: `php artisan route:list | grep filament` — `/admin/login` appears.

- [ ] 2. **Add `FilamentUser` interface to `app/Models/User.php`**
      Implement `\Filament\Models\Contracts\FilamentUser`. Add `canAccessPanel(Panel $panel): bool`
      that calls `$this->setOrganizationTeam()` then returns `$this->is_active &&`
      `($this->hasRole([...Arabic role names...]) || $this->hasPermissionTo('manage_organization'))`.
      Files: `app/Models/User.php`
      Verify: `php artisan test --stop-on-failure` — no new failures.

- [ ] 3. **Create four Policy classes**
      - `app/Policies/OrganizationPolicy.php` — viewAny/view: `manage_organization`; create/update/delete: `manage_organization`.
      - `app/Policies/UserPolicy.php` — all methods: `manage_users`.
      - `app/Policies/EmployeePolicy.php` — viewAny/view: `view_employees`; create: `create_employee`; update: `edit_employee`; delete: `delete_employee`.
      - `app/Policies/LeaveRequestPolicy.php` — viewAny/view: `view_leave_requests`; create: `create_leave_request`; update: `edit_leave_request` + `canBeEdited()`; approve: `approve_leave_request`; reject: `reject_leave_request`; return: `return_leave_request`; cancel: `cancel_leave_request` + `canBeCancelled()`.
      Each method calls `$user->setOrganizationTeam()` before the permission check.
      Files: `app/Policies/OrganizationPolicy.php`, `UserPolicy.php`, `EmployeePolicy.php`, `LeaveRequestPolicy.php`
      Verify: `php artisan test --stop-on-failure` — passes.

- [ ] 4. **Register policies in `app/Providers/AppServiceProvider.php`**
      Add `protected $policies = [Organization::class => OrganizationPolicy::class, ...]` and call
      `Gate::policy()` for each in `boot()`. Import `Illuminate\Support\Facades\Gate`.
      Files: `app/Providers/AppServiceProvider.php`
      Verify: `php artisan optimize:clear && php artisan test --stop-on-failure`.

---

## FEAT-002 — Catalog Resources (Org, User, Employee, LeaveType, Holiday) + BalanceBadge

- [ ] 5. **Create `app/Filament/Components/BalanceBadge.php` + blade view**
      A Filament `ViewComponent` that accepts an `Employee` model and renders per-type leave
      balance badges for the current year. The blade is at
      `resources/views/filament/components/balance-badge.blade.php`.
      Files: `app/Filament/Components/BalanceBadge.php`, `resources/views/filament/components/balance-badge.blade.php`
      Verify: file exists; `php artisan optimize:clear` with no errors.

- [ ] 6. **Create `app/Filament/Resources/OrganizationResource.php`**
      navigationGroup('المؤسسات والمستخدمون'). Table: id, name, code, type (enum label), parent name, is_active IconColumn.
      Form: TextInput name/code, Select type (OrganizationType cases), Select parent_id
      (`Organization::withoutGlobalScopes()`), Toggle is_active. Filters: type, is_active.
      Policy: OrganizationPolicy. Pages: List/Create/Edit.
      Files: `app/Filament/Resources/OrganizationResource.php` + three inner Page classes.
      Verify: `php artisan route:list | grep organizations`.

- [ ] 7. **Create `app/Filament/Resources/UserResource.php`**
      navigationGroup('المؤسسات والمستخدمون'). Table: name, email, organization name, is_active, roles.
      Form: name, email (unique), password (nullable on edit), organization_id (searchable Select,
      `withoutGlobalScopes`), is_active, roles (Select from Spatie roles for chosen org).
      Policy: UserPolicy. Pages: List/Create/Edit.
      Files: `app/Filament/Resources/UserResource.php` + three inner Page classes.
      Verify: `php artisan route:list | grep users`.

- [ ] 8. **Create `app/Filament/Resources/EmployeeResource.php`**
      navigationGroup('الموظفون'). Table: employee_code, full_name, job_title,
      entitlement_grade (label), organization name, is_active, BalanceBadge ViewColumn.
      Form: all Employee fillable fields; entitlement_grade Select shows `label() — N يوم` hints.
      Policy: EmployeePolicy. Pages: List/Create/Edit.
      Files: `app/Filament/Resources/EmployeeResource.php` + three inner Page classes.
      Verify: `php artisan route:list | grep employees`.

- [ ] 9. **Create `app/Filament/Resources/LeaveTypeResource.php`**
      navigationGroup('الإعدادات'). Simple CRUD — all LeaveType fillable fields.
      Gate-protected via `can('manage_organization')` on the resource level (no separate Policy class).
      Files: `app/Filament/Resources/LeaveTypeResource.php` + three Page classes.
      Verify: `php artisan route:list | grep leave-types`.

- [ ] 10. **Create `app/Filament/Resources/HolidayResource.php`**
       navigationGroup('الإعدادات'). Table: date (DateColumn), name, Arabic day-of-week (computed).
       Form: DatePicker date, TextInput name. Default sort: date ascending.
       Gate-protected via `can('manage_organization')`.
       Files: `app/Filament/Resources/HolidayResource.php` + three Page classes.
       Verify: `php artisan route:list | grep holidays`.

---

## FEAT-003 — LeaveRequestResource (full workflow integration)

- [ ] 11. **Create `app/Filament/Resources/LeaveRequestResource.php`** (skeleton + table)
       navigationGroup('طلبات الإجازات'). Table: number, employee full_name, leaveType name,
       start_date, end_date, days, status (BadgeColumn → label()/color()), current_stage, organization name.
       Default sort: created_at desc. NavigationBadge: `LeaveRequest::pending()->count()`.
       Files: `app/Filament/Resources/LeaveRequestResource.php`
       Verify: `php artisan route:list | grep leave-requests`.

- [ ] 12. **Add filters + scoped table query to ListLeaveRequests page**
       SelectFilter status, SelectFilter leave_type_id, SelectFilter organization_id (withoutGlobalScopes),
       DatePicker range filter on start_date. Override `getTableQuery()` in ListLeaveRequests:
       if user has `view_all_leave_requests`, scope to `$user->organization->subtreeIds()`;
       otherwise restrict to `$user->organization_id` only.
       Files: `app/Filament/Resources/LeaveRequestResource/Pages/ListLeaveRequests.php`
       Verify: `php artisan test --stop-on-failure`.

- [ ] 13. **Build the create/edit form schema**
       Section 'بيانات الطلب': Select employee_id (required, `Employee::active()`, searchable),
       Select leave_type_id (required, `LeaveType::active()`, reactive), DatePicker start_date
       (reactive), DatePicker end_date (reactive), TextInput days (numeric, disabled — computed
       from start/end via a live Placeholder), DatePicker written_at, Select substitute_employee_id
       (nullable), Textarea reason. Section 'معلومات الرصيد' (readonly Placeholder fields for
       balance_entitled/used/remaining — only visible on edit/view).
       Override `handleRecordCreation(array $data)` in CreateLeaveRequest page to call
       `app(LeaveRequestService::class)->create($data, Auth::user())` and persist the returned
       `CreateLeaveRequestResult->request`.
       Files: `app/Filament/Resources/LeaveRequestResource.php` (form schema),
              `app/Filament/Resources/LeaveRequestResource/Pages/CreateLeaveRequest.php`,
              `app/Filament/Resources/LeaveRequestResource/Pages/EditLeaveRequest.php`
       Verify: Create a draft via the UI without error; `php artisan test --stop-on-failure`.

- [ ] 14. **Create ViewLeaveRequest page with steps timeline**
       Extend `ViewRecord`. Infolist shows all request fields. Below the infolist, render a steps
       timeline using a `RepeatableEntry` on `steps` relation showing stage, status badge
       (StepStatus::color()/label()), actor name, acted_at, note.
       Blade partial: `resources/views/filament/leave-request-steps-timeline.blade.php`.
       Files: `app/Filament/Resources/LeaveRequestResource/Pages/ViewLeaveRequest.php`,
              `resources/views/filament/leave-request-steps-timeline.blade.php`
       Verify: `php artisan test --stop-on-failure`.

- [ ] 15. **Add all workflow actions (Submit, Approve, Reject, Return, Cancel)**
       All five actions are added as `->headerActions()` on ViewLeaveRequest and as
       `->actions()` rows on the table in ListLeaveRequests.
       Each action: resolves `$step = $record->steps()->where('status', StepStatus::PENDING)->where('stage', $record->current_stage)->first()` where needed; delegates to the matching `LeaveRequestService` method; catches `LeaveRequestException` and `ValidationException` and shows a Filament danger notification.
       Visibility guards use the `LeaveRequestPolicy` methods via `visible(fn($record) => Auth::user()->can('approve', $record))` pattern.
       Files: `app/Filament/Resources/LeaveRequestResource.php` (actions defined in `getHeaderActions()` and `table()->actions()`),
              `app/Filament/Resources/LeaveRequestResource/Pages/ViewLeaveRequest.php`
       Verify: `php artisan test --stop-on-failure`; manually test each action in browser.

---

## Final Verification

```bash
php artisan optimize:clear
php artisan route:list | grep filament
php artisan test --stop-on-failure
```

Expected: all existing tests pass; `/admin/*` routes present for all six resources.
