# Implementation Plan — قاعدة البيانات (School Database)

## Context from Codebase Exploration

- **Framework**: Laravel + Filament v3
- **Panel**: `admin` panel, discovered via `discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')` — **no changes needed to `AdminPanelProvider.php`**.
- **OrganizationType enum**: `DIRECTORATE`, `ADMINISTRATION`, `SCHOOL` (values: `'directorate'`, `'administration'`, `'school'`).
- **Global scopes**: `Organization`, `Employee`, `LeaveRequest` all use `OrganizationScope` and must be queried with `withoutGlobalScopes()`.
- **`canAccess()` pattern**: Matches `ReportsPage::canAccess()` — call `Auth::user()->setOrganizationTeam()`, then check org type.
- **Tab approach**: Use a `$activeTab` public Livewire property toggled by `wire:click` in the Blade view (no Filament tab component needed).
- **Route param for detail page**: Filament Pages do not auto-bind route parameters. `mount()` must call `request()->route('schoolId')` to capture the `{schoolId}` segment registered via `getRoutes()` override.
- **URL helpers**: `EmployeeResource::getUrl('edit', ['record' => $id])` and `LeaveRequestResource::getUrl('view', ['record' => $id])` — pattern confirmed in `EmployeeResource.php` and `PendingRequestsWidget.php`.
- **Status labels/colors**: Use `LeaveRequest::pendingApprovalLabel($stage)` for stage label and `$leaveRequest->status->label()` + `$leaveRequest->status->color()` from `LeaveStatus` enum.
- **Navigation group**: `'المؤسسات والمستخدمون'` — exact string registered in `AdminPanelProvider.php` line 43.

---

## Files to Create

| # | Path |
|---|------|
| 1 | `app/Filament/Pages/SchoolDatabasePage.php` |
| 2 | `app/Filament/Pages/SchoolDetailPage.php` |
| 3 | `resources/views/filament/pages/school-database-page.blade.php` |
| 4 | `resources/views/filament/pages/school-detail-page.blade.php` |

## Files to Modify

None — `AdminPanelProvider.php` uses `discoverPages()`, so both new page classes are auto-registered.

---

## Implementation Steps

- [ ] 1. **Create `SchoolDatabasePage.php`** — the index page listing school organizations.

  **What to do**: Create `App\Filament\Pages\SchoolDatabasePage` extending `Filament\Pages\Page`. It implements `HasTable` using `InteractsWithTable`. In `canAccess()` call `Auth::user()->setOrganizationTeam()` then check if the user's organization type is `ADMINISTRATION` or `DIRECTORATE`. In `mount()` call `abort_unless(static::canAccess(), 403)`. In `getTableQuery()` return schools that are direct children (by `parent_id`) of the current user's organization, using `Organization::withoutGlobalScopes()->where('type', OrganizationType::SCHOOL->value)->where('parent_id', $user->organization_id)`. For DIRECTORATE users, scope to `parent_id IN (subtreeIds of org where type = ADMINISTRATION)` — i.e., all administration children, then all their school children. Set navigation properties: `$navigationIcon = 'heroicon-o-circle-stack'`, `$navigationLabel = 'قاعدة البيانات'`, `$navigationGroup = 'المؤسسات والمستخدمون'`, `$navigationSort = 5`. The table should have columns: `name` (searchable), `code`, `is_active` (boolean icon). Each row has a URL action pointing to `SchoolDetailPage` built with `route('filament.admin.pages.school-detail-page', ['schoolId' => $record->id])` — or use the static helper `SchoolDetailPage::getUrl(['schoolId' => $record->id])`.

  **Files**: `app/Filament/Pages/SchoolDatabasePage.php`

  **Verify**: `php artisan route:list | grep school-database` shows the route. Log in as an ADMINISTRATION user and navigate to `/admin/school-database` — the page loads with the schools table.

---

- [ ] 2. **Create `SchoolDetailPage.php`** — the detail page for a single school with 4 tabs.

  **What to do**: Create `App\Filament\Pages\SchoolDetailPage` extending `Filament\Pages\Page`. Declare:
  - `public int $schoolId;`
  - `public string $activeTab = 'info';`
  - `protected static bool $shouldRegisterNavigation = false;` — hides it from the sidebar.
  - Override `getRoutes(): Closure` (static method) to register a custom route with `{schoolId}` parameter:
    ```php
    public static function getRoutes(): Closure
    {
        return function (\Illuminate\Routing\Router $router) {
            $router->get(static::getRoutePath(), static::class)
                ->name(static::getSlug());
        };
    }
    public static function getRoutePath(): string
    {
        return '/school-database/{schoolId}';
    }
    ```
  - In `mount()`: `$this->schoolId = (int) request()->route('schoolId'); abort_unless(static::canAccess(), 403); abort_if(! $this->schoolId, 404);`
  - `canAccess()` — same check as `SchoolDatabasePage`: ADMINISTRATION or DIRECTORATE org type only.
  - Compute `$school` in `mount()` with `Organization::withoutGlobalScopes()->where('type', OrganizationType::SCHOOL->value)->findOrFail($this->schoolId)`.
  - Expose view data via `getViewData()` (override), returning: `school`, `parentOrg`, `employeeCount`, `activeEmployeeCount`, `totalRequests`, `approvedCount`, `pendingCount`, `rejectedCount`, `employees` (paginated), `leaveRequests` (paginated).
  - All counts use `withoutGlobalScopes()`.
  - `employees` query: `Employee::withoutGlobalScopes()->with('entitlementGrade')->where('organization_id', $this->schoolId)->get()`
  - `leaveRequests` query: `LeaveRequest::withoutGlobalScopes()->with(['employee', 'leaveType'])->where('organization_id', $this->schoolId)->latest()->get()`
  - The page heading: `$school->name` returned from `getHeading()`.

  **Files**: `app/Filament/Pages/SchoolDetailPage.php`

  **Verify**: Navigate to `/admin/school-database/1` — the page loads without a 404 and `$schoolId` is `1`.

---

- [ ] 3. **Create Blade view `school-database-page.blade.php`** — renders the schools table.

  **What to do**: Create `resources/views/filament/pages/school-database-page.blade.php`. Wrap content in `<x-filament-panels::page>`. Inside, use `{{ $this->table }}` to render the Filament table. Add a breadcrumb-style heading showing "قاعدة البيانات — المدارس". The view path must match: `protected static string $view = 'filament.pages.school-database-page';` in the PHP class.

  **Files**: `resources/views/filament/pages/school-database-page.blade.php`

  **Verify**: The school index page at `/admin/school-database` renders the table without Blade errors.

---

- [ ] 4. **Create Blade view `school-detail-page.blade.php`** — renders the 4-tab detail page.

  **What to do**: Create `resources/views/filament/pages/school-detail-page.blade.php`. The view path must match `protected static string $view = 'filament.pages.school-detail-page';`. Structure:

  ```
  <x-filament-panels::page>
    <div dir="rtl">

      {{-- Tab navigation --}}
      <div class="flex gap-2 border-b mb-4">
        <button wire:click="$set('activeTab', 'info')" ...>بيانات المدرسة</button>
        <button wire:click="$set('activeTab', 'employees')" ...>الموظفون</button>
        <button wire:click="$set('activeTab', 'requests')" ...>طلبات الإجازة</button>
        <button wire:click="$set('activeTab', 'stats')" ...>الإحصائيات</button>
      </div>

      {{-- Tab: بيانات المدرسة --}}
      @if($activeTab === 'info')
        <x-filament::section heading="بيانات المدرسة">
          <dl>
            <dt>الاسم</dt><dd>{{ $school->name }}</dd>
            <dt>الكود</dt><dd>{{ $school->code }}</dd>
            <dt>الحالة</dt><dd>{{ $school->is_active ? 'نشط' : 'غير نشط' }}</dd>
            <dt>الإدارة التعليمية</dt><dd>{{ $parentOrg?->name ?? '—' }}</dd>
            <dt>عدد الموظفين</dt><dd>{{ $employeeCount }}</dd>
            <dt>عدد الطلبات</dt><dd>{{ $totalRequests }}</dd>
          </dl>
        </x-filament::section>
      @endif

      {{-- Tab: الموظفون --}}
      @if($activeTab === 'employees')
        <x-filament::section heading="الموظفون">
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th>الكود</th><th>الاسم</th><th>المسمى الوظيفي</th>
                <th>الدرجة</th><th>نشط</th><th>إجراء</th>
              </tr>
            </thead>
            <tbody>
              @foreach($employees as $employee)
                <tr>
                  <td>{{ $employee->employee_code }}</td>
                  <td>{{ $employee->full_name }}</td>
                  <td>{{ $employee->job_title ?? '—' }}</td>
                  <td>{{ $employee->entitlementGrade?->name ?? '—' }}</td>
                  <td>{{ $employee->is_active ? '✓' : '✗' }}</td>
                  <td>
                    <a href="{{ \App\Filament\Resources\EmployeeResource::getUrl('edit', ['record' => $employee->id]) }}"
                       class="text-primary-600 underline">تعديل</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </x-filament::section>
      @endif

      {{-- Tab: طلبات الإجازة --}}
      @if($activeTab === 'requests')
        <x-filament::section heading="طلبات الإجازة">
          {{-- Status filter via wire:model --}}
          <div class="mb-3">
            <select wire:model.live="statusFilter" class="filament-forms-input">
              <option value="">— كل الحالات —</option>
              @foreach(\App\Enums\LeaveStatus::cases() as $s)
                <option value="{{ $s->value }}">{{ $s->label() }}</option>
              @endforeach
            </select>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th>الرقم</th><th>الموظف</th><th>النوع</th>
                <th>من</th><th>إلى</th><th>الأيام</th>
                <th>الحالة</th><th>المرحلة</th><th>إجراء</th>
              </tr>
            </thead>
            <tbody>
              @foreach($leaveRequests as $lr)
                <tr>
                  <td>{{ $lr->number }}</td>
                  <td>{{ $lr->employee?->full_name }}</td>
                  <td>{{ $lr->leaveType?->name }}</td>
                  <td>{{ $lr->start_date?->format('Y-m-d') }}</td>
                  <td>{{ $lr->end_date?->format('Y-m-d') }}</td>
                  <td>{{ $lr->days }}</td>
                  <td>
                    <x-filament::badge :color="$lr->status->color()">
                      {{ $lr->status->label() }}
                    </x-filament::badge>
                  </td>
                  <td>{{ \App\Models\LeaveRequest::pendingApprovalLabel($lr->current_stage) }}</td>
                  <td>
                    <a href="{{ \App\Filament\Resources\LeaveRequestResource::getUrl('view', ['record' => $lr->id]) }}"
                       class="text-primary-600 underline">عرض</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </x-filament::section>
      @endif

      {{-- Tab: الإحصائيات --}}
      @if($activeTab === 'stats')
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold">{{ $employeeCount }}</p>
              <p class="text-sm text-gray-500">إجمالي الموظفين</p>
            </div>
          </x-filament::section>
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold text-success-600">{{ $activeEmployeeCount }}</p>
              <p class="text-sm text-gray-500">الموظفون النشطون</p>
            </div>
          </x-filament::section>
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold">{{ $totalRequests }}</p>
              <p class="text-sm text-gray-500">إجمالي الطلبات</p>
            </div>
          </x-filament::section>
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold text-success-600">{{ $approvedCount }}</p>
              <p class="text-sm text-gray-500">معتمدة</p>
            </div>
          </x-filament::section>
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold text-warning-600">{{ $pendingCount }}</p>
              <p class="text-sm text-gray-500">قيد المراجعة / بانتظار</p>
            </div>
          </x-filament::section>
          <x-filament::section>
            <div class="text-center">
              <p class="text-3xl font-bold text-danger-600">{{ $rejectedCount }}</p>
              <p class="text-sm text-gray-500">مرفوضة</p>
            </div>
          </x-filament::section>
        </div>
      @endif

    </div>
  </x-filament-panels::page>
  ```

  Also add `public string $statusFilter = '';` as a public Livewire property in `SchoolDetailPage.php`, and filter `$leaveRequests` in `getViewData()` when `$this->statusFilter` is non-empty: `->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))`.

  **Files**: `resources/views/filament/pages/school-detail-page.blade.php`

  **Verify**: Open `/admin/school-database/{id}`, click each tab, confirm content switches without full page reload (Livewire `wire:click`). Filter by status on the requests tab.

---

## Class Specifications

### `SchoolDatabasePage`

```php
namespace App\Filament\Pages;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SchoolDatabasePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'قاعدة البيانات';
    protected static ?string $navigationGroup = 'المؤسسات والمستخدمون';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view = 'filament.pages.school-database-page';
    protected static string  $slug = 'school-database';        // → /admin/school-database

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $user?->setOrganizationTeam();
        $type = $user?->organization?->type;
        return $type === OrganizationType::ADMINISTRATION
            || $type === OrganizationType::DIRECTORATE;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        $org  = $user?->organization;

        $query = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::SCHOOL->value);

        if ($org?->isAdministration()) {
            $query->where('parent_id', $org->id);
        } elseif ($org?->isDirectorate()) {
            // All schools whose parent is an administration under this directorate
            $adminIds = Organization::withoutGlobalScopes()
                ->where('parent_id', $org->id)
                ->where('type', OrganizationType::ADMINISTRATION->value)
                ->pluck('id');
            $query->whereIn('parent_id', $adminIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المدرسة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->recordUrl(fn (Organization $record): string =>
                SchoolDetailPage::getUrl(['schoolId' => $record->id])
            )
            ->emptyStateHeading('لا توجد مدارس');
    }
}
```

### `SchoolDetailPage`

```php
namespace App\Filament\Pages;

use App\Enums\LeaveStatus;
use App\Enums\OrganizationType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Organization;
use Closure;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SchoolDetailPage extends Page
{
    protected static bool   $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.school-detail-page';

    public int    $schoolId   = 0;
    public string $activeTab  = 'info';
    public string $statusFilter = '';

    // Route registration — override to add {schoolId} segment
    public static function getRoutes(): Closure
    {
        return function (\Illuminate\Routing\Router $router): void {
            $router->get('/school-database/{schoolId}', static::class)
                   ->middleware(static::getRouteMiddleware(app()))
                   ->name((new static)->getRouteName());
        };
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $user?->setOrganizationTeam();
        $type = $user?->organization?->type;
        return $type === OrganizationType::ADMINISTRATION
            || $type === OrganizationType::DIRECTORATE;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->schoolId = (int) request()->route('schoolId');
        abort_if(! $this->schoolId, 404);
    }

    public function getHeading(): string
    {
        return Organization::withoutGlobalScopes()->find($this->schoolId)?->name
            ?? 'بيانات المدرسة';
    }

    protected function getViewData(): array
    {
        $school = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::SCHOOL->value)
            ->findOrFail($this->schoolId);

        $parentOrg = $school->parent_id
            ? Organization::withoutGlobalScopes()->find($school->parent_id)
            : null;

        $employeeCount       = Employee::withoutGlobalScopes()->where('organization_id', $this->schoolId)->count();
        $activeEmployeeCount = Employee::withoutGlobalScopes()->where('organization_id', $this->schoolId)->where('is_active', true)->count();

        $requestBase    = LeaveRequest::withoutGlobalScopes()->where('organization_id', $this->schoolId);
        $totalRequests  = (clone $requestBase)->count();
        $approvedCount  = (clone $requestBase)->where('status', LeaveStatus::APPROVED->value)->count();
        $pendingCount   = (clone $requestBase)->whereIn('status', [LeaveStatus::SUBMITTED->value, LeaveStatus::IN_REVIEW->value])->count();
        $rejectedCount  = (clone $requestBase)->where('status', LeaveStatus::REJECTED->value)->count();

        $employees = Employee::withoutGlobalScopes()
            ->with('entitlementGrade')
            ->where('organization_id', $this->schoolId)
            ->orderBy('full_name')
            ->get();

        $leaveRequests = LeaveRequest::withoutGlobalScopes()
            ->with(['employee', 'leaveType'])
            ->where('organization_id', $this->schoolId)
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->get();

        return compact(
            'school', 'parentOrg',
            'employeeCount', 'activeEmployeeCount',
            'totalRequests', 'approvedCount', 'pendingCount', 'rejectedCount',
            'employees', 'leaveRequests'
        );
    }
}
```

---

## Key Design Decisions

1. **Route parameter capture**: Filament Pages do not inject route model binding into page properties automatically. The plan uses `request()->route('schoolId')` inside `mount()`. The custom `getRoutes()` Closure registers `/school-database/{schoolId}` under the admin panel's middleware and naming convention. This matches how Filament itself registers sub-resource routes internally.

2. **No `HasTable` on detail page**: The detail page uses plain Blade tables (foreach loops) rather than Filament Table components, because the data is scoped to a single school and does not need server-side pagination or complex filter widgets. This keeps the component lighter and avoids registering multiple table instances on one Livewire component.

3. **`$shouldRegisterNavigation = false`**: The detail page is reachable only via a row click from `SchoolDatabasePage`, not through the sidebar.

4. **Tab switching**: `wire:click="$set('activeTab', 'info')"` triggers a Livewire component re-render with the new `$activeTab` value, which the Blade `@if` blocks use to show/hide sections. No JavaScript needed.

5. **Status filter**: `statusFilter` is a public Livewire property bound with `wire:model.live` on a `<select>` element inside the requests tab. The `getViewData()` method applies `->when($this->statusFilter, ...)` so the list re-queries on each change.

6. **DIRECTORATE scoping**: A DIRECTORATE user sees schools that belong to administration children of their org, not just direct children. This matches the existing `subtreeIds()` pattern in `ReportsPage.php`.

7. **Navigation**: Group `'المؤسسات والمستخدمون'` is the exact string registered in `AdminPanelProvider.php`. Sort `5` places it after existing items in that group.
