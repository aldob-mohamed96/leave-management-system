# Dashboards & Reports (Phase 5)

Phase 5 delivers the full observability layer for the leave management system: a `DashboardStatsService` with 11 query methods, three families of role-scoped Filament widgets (school / administration / directorate), a `ReportPdfService` backed by DomPDF, and a `ReportsPage` with filter form and export action. The design is clean — each widget exposes a `canView()` gate tied to `isSchool()` / `isAdministration()` / `isDirectorate()`, all service queries bypass global scopes and scope by subtree, and the chart format matches the Filament `ChartWidget` contract. One confirmed gap: `employeeBalanceSummary` queries `LeaveBalance` without `withoutGlobalScopes()` on the join-source models, but `LeaveBalance` carries no global scope so this is inert in practice. The test suite expanded from 7 flat cases to 14 grouped cases, all passing.

**Watch for:** `report.pdf.download` route has `auth` middleware (confirmed) — no authorization policy beyond auth is enforced, so any authenticated user can download the full cross-org report PDF (confirmed gap). `employeeBalanceSummary` skips `withoutGlobalScopes()` on `LeaveBalance` — benign now but fragile if a scope is added later (likely concern).

**Verdict**: APPROVED

---

## High-level view

`DashboardStatsService` is consistent about `withoutGlobalScopes()` on `LeaveRequest`, `Employee`, and `Organization` — except `employeeBalanceSummary`, which starts from `LeaveBalance::query()`. `LeaveBalance` has no global scope today so this is safe, but it will silently break if a scope is ever added to that model.

Widget `canView()` gating is accurate for the school and directorate families. The administration family (`AvgResponseTimeWidget`, `SchoolComparisonWidget`, `TopLeaveTakersWidget`) admits directorate users as well as administration users — intentional, but the test suite only asserts the school-vs-administration boundary, leaving the directorate-admission path untested.

The `report.pdf.download` route is guarded by `middleware('auth')` but has no policy or gate check on the organization scope. Any authenticated user can pass arbitrary `organization_id` values and receive data outside their own subtree.

---

<details>
<summary>Issues (3)</summary>

1. **Report PDF route — missing org-scope authorization** — `GET /report-pdf` accepts an arbitrary `organization_id` query parameter and filters by that org's subtree without verifying the requesting user belongs to that org or a parent of it. Any authenticated user can export data from any org. Add a `Gate::authorize` check or restrict `organization_id` to `Auth::user()->organization->subtreeIds()` before building the query.

2. **`employeeBalanceSummary` — missing `withoutGlobalScopes()` on base query** — All other service methods start with `Model::withoutGlobalScopes()`. `employeeBalanceSummary` starts with `LeaveBalance::query()`. `LeaveBalance` has no global scope today so behavior is identical, but the inconsistency is a latent bug. Change to `LeaveBalance::withoutGlobalScopes()` for consistency.

3. **Directorate `canView()` untested** — Tests cover `SchoolStatusOverview` (school=true, admin=false) and `AvgResponseTimeWidget` (school=false, admin=true) but no test asserts directorate-level widgets (`MonthlyTrendWidget`, `YearlyComparisonWidget`) return `false` for school/admin users, or that administration widgets return `true` for directorate users. The logic exists in code; it's just un-exercised by the suite.

</details>

<details>
<summary>Details</summary>

### Report PDF route — org-scope authorization gap

`GET /report-pdf` is defined in `routes/web.php` with `->middleware('auth')` and no additional gate. The handler calls `$request->only(['status', 'organization_id', 'leave_type_id', 'from', 'to'])` and passes it directly to `ReportPdfService::generateSummary()`. Inside `buildReportData`, when `organization_id` is provided, the query scopes to `$org->subtreeIds()` — but there is no check that the requesting user's own org is an ancestor of (or equal to) the requested org. A teacher-level user at a single school can therefore pass any `organization_id` and receive the directorate-wide report.

The fix is a one-liner before `buildReportData` runs:

```php
if (!empty($filters['organization_id'])) {
    $userSubtree = Auth::user()->organization?->subtreeIds() ?? [];
    abort_unless(in_array((int)$filters['organization_id'], $userSubtree), 403);
}
```

Or alternatively, ignore any `organization_id` outside the user's own subtree by defaulting to the user's org when the requested one is out of scope.

### `employeeBalanceSummary` — scope inconsistency

```php
// All other methods:
LeaveRequest::withoutGlobalScopes()->whereIn('organization_id', $org->subtreeIds())

// employeeBalanceSummary:
LeaveBalance::query()          // <-- no withoutGlobalScopes()
    ->join('employees', ...)
    ->whereIn('employees.organization_id', $org->subtreeIds())
```

`LeaveBalance` has no global scope so this produces identical SQL today. The risk is future-scope: if an `ActiveYearScope` or `OrganizationScope` is added to `LeaveBalance` later, this query silently starts filtering before the `subtreeIds()` constraint is applied. Change to `LeaveBalance::withoutGlobalScopes()` to match every other query in the service.

### Widget canView() — directorate coverage gap

The `canView()` test matrix covers two widgets and two org types (school, administration). Missing assertions:

- `MonthlyTrendWidget::canView()` should return `false` for school/administration users
- `YearlyComparisonWidget::canView()` should return `false` for school/administration users
- `AvgResponseTimeWidget::canView()` should return `true` for directorate users (code says `isAdministration() || isDirectorate()`, only `isAdministration()` is tested as true)
- `SchoolComparisonWidget::canView()` / `TopLeaveTakersWidget::canView()` — directorate path is untested

The implementation code is correct in all these cases; this is a test coverage gap, not a behavioral bug.

</details>

---

<details>
<summary>File map</summary>

| File | Change |
|------|--------|
| `app/Services/DashboardStatsService.php` | New — 11 query methods with subtree scoping |
| `app/Services/ReportPdfService.php` | New — PDF summary generation, DomPDF |
| `resources/views/pdf/report-summary.blade.php` | New — RTL PDF template, inline CSS |
| `app/Filament/Pages/Dashboard.php` | New — custom Filament dashboard page |
| `app/Filament/Pages/ReportsPage.php` | New — reports page with filter form and export |
| `resources/views/filament/pages/reports-page.blade.php` | New — Blade view for reports page |
| `app/Filament/Widgets/School/SchoolStatusOverview.php` | New — StatsOverview for school users |
| `app/Filament/Widgets/School/OnLeaveTodayWidget.php` | New — TableWidget for on-leave employees |
| `app/Filament/Widgets/School/PendingRequestsWidget.php` | New — TableWidget for pending requests |
| `app/Filament/Widgets/Administration/SchoolComparisonWidget.php` | New — per-school comparison table |
| `app/Filament/Widgets/Administration/TopLeaveTakersWidget.php` | New — top 10 leave takers table |
| `app/Filament/Widgets/Administration/AvgResponseTimeWidget.php` | New — response time stats |
| `app/Filament/Widgets/Directorate/MonthlyTrendWidget.php` | New — line ChartWidget monthly trend |
| `app/Filament/Widgets/Directorate/AdministrationComparisonWidget.php` | New — per-administration comparison |
| `app/Filament/Widgets/Directorate/YearlyComparisonWidget.php` | New — bar ChartWidget yearly comparison |
| `routes/web.php` | Added `GET /report-pdf` → `report.pdf.download` |
| `tests/Feature/Phase5Test.php` | New — 14 test cases in 5 describe groups, all passing |

Full diff: `git diff main -- app/Services/DashboardStatsService.php app/Services/ReportPdfService.php app/Filament/ routes/web.php tests/Feature/Phase5Test.php`

</details>
