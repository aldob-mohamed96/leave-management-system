# Implementation Plan — Phase 5: Dashboards & Reports

- [ ] 1. Create `DashboardStatsService` with all query methods.
      This is the data layer every widget and report depends on. All queries use
      `LeaveRequest::withoutGlobalScopes()` and `Organization::withoutGlobalScopes()`,
      scoped by `path LIKE $org->path . '%'` via join or `whereIn(subtreeIds())`.
      Files: `app/Services/DashboardStatsService.php`
      Verify: `./vendor/bin/pest tests/Feature/Phase5Test.php --compact` — tests 1–5 pass.

- [ ] 2. Create `ReportPdfService` and its Blade template.
      Generates a summary PDF binary using Barryvdh\DomPDF\Facade\Pdf; same inline-CSS
      DejaVu Sans RTL style as `resources/views/pdf/leave-request.blade.php`.
      Files:
        - `app/Services/ReportPdfService.php`
        - `resources/views/pdf/report-summary.blade.php`
      Verify: `./vendor/bin/pest tests/Feature/Phase5Test.php --compact` — test 6 passes.

- [ ] 3. Create School-level Filament widgets (3 widgets).
      All extend the correct base class, carry `$sort`, implement `canView()` checking
      `auth()->user()->organization->isSchool()`, and call `DashboardStatsService`.
      Files:
        - `app/Filament/Widgets/School/SchoolStatusOverview.php`
        - `app/Filament/Widgets/School/OnLeaveTodayWidget.php`
        - `app/Filament/Widgets/School/PendingRequestsWidget.php`
      Verify: `./vendor/bin/pest tests/Feature/Phase5Test.php --compact` — test 7 passes;
              `php artisan route:list | grep admin` exits 0.

- [ ] 4. Create Administration-level Filament widgets (3 widgets).
      `canView()` allows both administration AND directorate org users.
      Files:
        - `app/Filament/Widgets/Administration/SchoolComparisonWidget.php`
        - `app/Filament/Widgets/Administration/TopLeaveTakersWidget.php`
        - `app/Filament/Widgets/Administration/AvgResponseTimeWidget.php`
      Verify: `php artisan route:list | grep admin` exits 0 (panel boots clean).

- [ ] 5. Create Directorate-level Filament widgets (3 widgets).
      Line chart (`MonthlyTrendWidget`), table (`AdministrationComparisonWidget`),
      bar chart (`YearlyComparisonWidget`). ChartWidget data format:
      `['datasets' => [['label' => '...', 'data' => [...]]], 'labels' => [...]]`.
      Files:
        - `app/Filament/Widgets/Directorate/MonthlyTrendWidget.php`
        - `app/Filament/Widgets/Directorate/AdministrationComparisonWidget.php`
        - `app/Filament/Widgets/Directorate/YearlyComparisonWidget.php`
      Verify: `php artisan route:list | grep admin` exits 0.

- [ ] 6. Create custom Dashboard page and update `AdminPanelProvider`.
      Extends `Filament\Pages\Dashboard`, sets heading to 'لوحة تحكم نظام الإجازات'.
      Replace `Pages\Dashboard::class` in the `->pages([])` array with the new class.
      Files:
        - `app/Filament/Pages/Dashboard.php`
        - `app/Providers/Filament/AdminPanelProvider.php` (update `pages()` array)
      Verify: `php artisan route:list | grep admin/dashboard` shows the route.

- [ ] 7. Create `ReportsPage` Filament page with filter form and export actions.
      Navigation group 'التقارير', icon 'heroicon-o-chart-bar', sort 10.
      Form: from/to date fields, organization_id select, leave_type_id select, status select.
      Actions: 'تصدير Excel' → `Excel::download(new LeaveRequestsExport($filters), ...)`;
               'تصدير PDF' → `ReportPdfService::generateSummary($filters)`.
      Files:
        - `app/Filament/Pages/ReportsPage.php`
        - `resources/views/filament/pages/reports-page.blade.php`
      Verify: `php artisan route:list | grep reports` shows the page route.

- [ ] 8. Write `tests/Feature/Phase5Test.php` with all 7 test cases.
      Follows Pest + RefreshDatabase pattern from `Phase4Test.php`; uses `createHierarchy()`
      helper from `Pest.php`; no Auth::user() calls in service tests (pass org directly).
      Files: `tests/Feature/Phase5Test.php`
      Verify: `./vendor/bin/pest tests/Feature/Phase5Test.php --compact` — all 7 tests pass.

- [ ] 9. Run the full test suite and fix any failures.
      Run: `cd /Users/mohamedgaber/projects/vaccancy/leave-management-system && ./vendor/bin/pest tests/ --compact`
      Expected: all tests pass (257+ assertions). Fix any failures in-place before stopping.
