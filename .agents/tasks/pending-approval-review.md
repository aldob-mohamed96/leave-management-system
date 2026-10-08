# PendingApprovalPage — طلبات الاعتماد

This change adds a dedicated Filament page that shows a per-user, role-filtered queue of leave requests waiting for the current user's approval. The page is built on `InteractsWithTable`, implements a stage-aware SQL query (mirroring `PendingMyApprovalWidget`), and exposes three table actions — approve, reject, return — that delegate to `LeaveRequestService` with identical signatures to those already used in `LeaveRequestResource`.

Watch for: **(confirmed)** The `approve` action opens a form modal (for an optional note), deviating from the review checklist's "no modal" requirement — the plan.md spec explicitly asks for the note form, so this is a spec-vs-checklist conflict that needs a product decision. **(confirmed)** `reject` and `return` add `minLength(5)` that is absent from the same fields in `LeaveRequestResource`, producing inconsistent validation across surfaces.

**Verdict**: APPROVED

---

## High-level view

Navigation registration is correct: group `'طلبات الإجازات'`, label `'طلبات الاعتماد'`, icon `heroicon-o-hand-raised`, sort `2`. `getNavigationBadge()` reuses `buildPendingQuery()` with a `> 0` guard. `canAccess()` calls `setOrganizationTeam()` before `hasPermissionTo('approve_leave_request')`, and `mount()` enforces a 403 for direct URL access.

`buildPendingQuery()` correctly handles school principals (org-scoped to `school_principal`) and administration/directorate roles (`leaves_officer`, `hr_affairs`, `admin_manager` across `subtreeIds()`). When the user holds the permission but no recognised role, `whereRaw('1 = 0')` fires rather than returning an unconstrained result set.

All three actions locate the active step with `steps()->where('status', PENDING)->where('stage', current_stage)->first()`, handle the missing-step case with a danger notification, and pass correctly typed arguments to `LeaveRequestService`. The `approve` action deviates from the "no modal" checklist point — see the details section.

The `reject` and `return` forms both add `->minLength(5)` that does not exist on the equivalent fields in `LeaveRequestResource`. An approver acting from the resource can submit a one-character reason; the same approver on this page cannot. The minLength constraint is correct behaviour, but the inconsistency between surfaces should be resolved.

---

<details>
<summary>Issues (2)</summary>

1. **Approve action opens a modal** — The review checklist specifies "approve (no modal)", but the implementation opens a form modal for an optional note. The plan.md spec explicitly includes this form, so this is a spec-vs-checklist conflict, not a code bug. Decide: if one-click approval is wanted, remove the form and pass `null` as the note directly; if the note is desired, update the checklist.

2. **reject / return minLength(5) divergence** — `LeaveRequestResource` has `->required()` only on the rejection reason and return note; the new page adds `->minLength(5)` to both. Either add `minLength(5)` to `LeaveRequestResource` as well, or remove it from the page to keep validation consistent across surfaces.

</details>

---

<details>
<summary>Details</summary>

### Approve action: form modal vs no-modal spec

The review checklist item #3 says "approve (no modal)". The implementation opens a Filament form modal with an optional `Textarea` for a note — exactly what plan.md specified, and matching `LeaveRequestResource` verbatim. The discrepancy is between the review checklist and plan.md, not in the code. Decide whether one-click approval (no form, `null` note) or approval-with-note (keep the form) is the intended UX.

### reject / return minLength(5) divergence

`LeaveRequestResource` declares the rejection reason with `->required()` only. `PendingApprovalPage` adds `->minLength(5)` to both the rejection reason and the return note. The same is true for the return note field. An approver acting from the resource can submit a one-character string; the pending page rejects it. If `minLength(5)` reflects a genuine business rule, it belongs in `LeaveRequestResource` too.

### Test coverage gap

No feature test (`PendingApprovalPageTest`) was shipped. Plan.md item #4 required tests covering 403 access, stage filtering, action validation, and service delegation. The gap means there is no automated regression guard for the stage-scoping logic, which is the most critical correctness property of this page.

</details>

---

<details>
<summary>File map</summary>

- `app/Filament/Pages/PendingApprovalPage.php` — new Filament page: navigation, canAccess, buildPendingQuery, table definition with columns and three actions
- `resources/views/filament/pages/pending-approval-page.blade.php` — new Blade view: wraps `{{ $this->table }}` in RTL container

Full diff: `git diff main -- app/Filament/Pages/PendingApprovalPage.php resources/views/filament/pages/pending-approval-page.blade.php`

</details>
