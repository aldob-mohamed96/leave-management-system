# Pending Approval Widget and Stage Label Helper

A new `PendingMyApprovalWidget` surfaces how many leave requests are waiting at each approval stage for the currently-logged-in administration user. A companion `pendingApprovalLabel()` static method was added to `LeaveRequest` and wired into the two `current_stage` display columns of `LeaveRequestResource`, replacing the neutral `stageLabel()` there with action-oriented labels ("بانتظار اعتماد مسؤول الإجازات"). A sort collision between Administration and Directorate widgets that appeared in the initial commit was resolved in a follow-up commit (89fc6aa) before this review completed.

Watch for: nothing blocking. All criteria verified against HEAD.

**Verdict**: APPROVED

---

## High-level view

`setOrganizationTeam()` is called before `hasRole()` in `getStats()`, satisfying the Spatie team-permission requirement. `canView()` gates on `$org->isAdministration() || $org->isDirectorate()` — an org-type check that doesn't involve role evaluation, so it correctly does not need the team-scoping call.

`pendingApprovalLabel()` is purely additive: `stageLabel()` is untouched, and the steps-history `RepeatableEntry` still calls `stageLabel()`, so the neutral historical label there is preserved. Only the two live `current_stage` display columns (table and infolist) were switched to the action-phrase variant.

The count query in the widget bypasses the global `OrganizationScope` via `withoutGlobalScopes()` and re-scopes explicitly to the user's `subtreeIds()`, which is the correct pattern for this codebase. The `SUBMITTED` + `IN_REVIEW` status filter matches the `scopePending()` definition in the model.

Sort order across all dashboard widgets is now clean: School 1–3, Administration 4–7, Directorate 8–10. The collision at slot 7 that existed between `AvgResponseTimeWidget` and `MonthlyTrendWidget` after the initial commit was fixed in commit `89fc6aa` (Directorate series bumped to 8, 9, 10). `Dashboard::getWidgets()` lists `PendingMyApprovalWidget` before the other Administration widgets, consistent with its `$sort = 4`.

<details>
<summary>Issues (0)</summary>

No blocking concerns.

</details>

<details>
<summary>Details</summary>

### setOrganizationTeam ordering and canView scoping

`getStats()` calls `$user->setOrganizationTeam()` unconditionally before entering the `$roleStageMap` loop, so every `hasRole()` call inside that loop operates with the correct Spatie team context. `canView()` uses only `$org->isAdministration()` and `$org->isDirectorate()` — neither involves Spatie role resolution — so it does not need the team-scoping call, and the absence of it there is correct.

### pendingApprovalLabel vs stageLabel

The match arms in `pendingApprovalLabel()` cover all four production stages (`leaves_officer`, `hr_affairs`, `admin_manager`, `school_principal`/`direct_manager`) plus the null → `'—'` fallback. The `school_principal` and `direct_manager` aliases both map to the same Arabic label, consistent with how `stageLabel()` handles them. `stageLabel()` itself was not modified; the steps-history infolist section still calls it, preserving the neutral historical display.

### Widget count query correctness

The query uses `LeaveRequest::withoutGlobalScopes()` to escape the global `OrganizationScope`, then immediately constrains `organization_id` to `subtreeIds()` — the same pattern used in `getEloquentQuery()` on the resource. The status filter (`SUBMITTED`, `IN_REVIEW`) is the exact same set as `scopePending()`, so the widget count is consistent with the navigation badge.

If a user holds multiple roles (e.g., both `مسؤول الإجازات` and `شؤون عاملين`), the widget renders one stat card per matching role. This is correct behavior — the user genuinely needs to act at both stages — and each stat reflects the queue at that specific stage.

### Sort ordering

After both commits, the full sort sequence is:

```
School:          SchoolStatusOverview=1, OnLeaveTodayWidget=2, PendingRequestsWidget=3
Administration:  PendingMyApprovalWidget=4, SchoolComparisonWidget=5, TopLeaveTakersWidget=6, AvgResponseTimeWidget=7
Directorate:     MonthlyTrendWidget=8, AdministrationComparisonWidget=9, YearlyComparisonWidget=10
```

No duplicates. `getWidgets()` insertion order matches sort order within each group.

</details>

<details>
<summary>File map</summary>

| File | Change |
|---|---|
| `app/Filament/Widgets/Administration/PendingMyApprovalWidget.php` | New widget — counts pending requests per approval stage for the current user |
| `app/Filament/Pages/Dashboard.php` | Import + registration of `PendingMyApprovalWidget`; comment updated to "sort 4–7" |
| `app/Models/LeaveRequest.php` | New `pendingApprovalLabel()` static method added after `stageLabel()` |
| `app/Filament/Resources/LeaveRequestResource.php` | `current_stage` TextColumn and TextEntry both switched from `stageLabel` to `pendingApprovalLabel` |
| `app/Filament/Widgets/Directorate/MonthlyTrendWidget.php` | `$sort` 7 → 8 (collision fix) |
| `app/Filament/Widgets/Directorate/AdministrationComparisonWidget.php` | `$sort` 8 → 9 (collision fix) |
| `app/Filament/Widgets/Directorate/YearlyComparisonWidget.php` | `$sort` 9 → 10 (collision fix) |

Full diff: `git show 58d54d8` (feature) and `git show 89fc6aa` (sort fix)

</details>
