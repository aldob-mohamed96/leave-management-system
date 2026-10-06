# Implementation Plan — Leave Management REST API

> Codebase: Laravel 11.57, PHP 8.2, Pest test suite (308 passing tests, SQLite in-memory for tests)
> All new code goes under `app/Http/Controllers/Api/`, `app/Http/Requests/Api/`, `app/Http/Resources/`, `routes/api.php`
> Sanctum is **not yet installed** — that is step 1.
> Existing Pest tests must stay green throughout.

---

## Pre-work note — `personal_access_tokens` migration

Sanctum ships its own migration (`create_personal_access_tokens_table`). Run it as part of step 1. No other schema changes are needed for the features below. The `notifications` table already exists (migration `2026_10_05_141559_create_notifications_table`).

---

- [ ] 1. Install and configure `laravel/sanctum`

  Sanctum is absent from `composer.json`. Install it, publish the config, and wire the `api` guard.

  **What to do:**
  1. Run `composer require laravel/sanctum:^4.0`.
  2. Run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` — publishes `config/sanctum.php` and the `create_personal_access_tokens_table` migration.
  3. Run `php artisan migrate` (development DB only; tests use in-memory SQLite that runs all migrations fresh via `RefreshDatabase`).
  4. In `config/auth.php`, verify (or add) the `api` guard:
     ```php
     'guards' => [
         // …existing web guard…
         'api' => [
             'driver'   => 'sanctum',
             'provider' => 'users',
         ],
     ],
     ```
  5. In `bootstrap/app.php`, add the `api` route file to `withRouting` and add Sanctum's middleware to the `api` group inside `withMiddleware`:
     ```php
     ->withRouting(
         web:      __DIR__.'/../routes/web.php',
         api:      __DIR__.'/../routes/api.php',
         commands: __DIR__.'/../routes/console.php',
         health:   '/up',
     )
     ->withMiddleware(function (Middleware $middleware) {
         $middleware->statefulApi(); // enables Sanctum cookie-based stateful for SPA; harmless for token-only clients
     })
     ```
  6. Create the empty `routes/api.php` file (just the opening `<?php` for now — routes are added in step 6).

  **Files to create/modify:**
  - `composer.json` / `composer.lock` (via `composer require`)
  - `config/sanctum.php` (published)
  - `config/auth.php`
  - `bootstrap/app.php`
  - `routes/api.php` (create)
  - `database/migrations/YYYY_MM_DD_XXXXXX_create_personal_access_tokens_table.php` (published)

  **Verify:** `php artisan migrate:status` shows the `personal_access_tokens` table as `Ran`. `php vendor/bin/pest` — all 308 existing tests still pass.

---

- [ ] 2. Add `HasApiTokens` to the `User` model

  Sanctum requires this trait on the authenticatable model. It must be added alongside the existing `HasRoles`, `HasFactory`, `Notifiable`, and `LogsActivity` traits without removing any of them.

  **What to do:**
  1. Add `use Laravel\Sanctum\HasApiTokens;` to the imports in `app/Models/User.php`.
  2. Add `HasApiTokens` to the `use` trait list inside the class body (before `HasFactory`).

  **Files to modify:**
  - `app/Models/User.php`

  **Verify:** `php vendor/bin/pest` — all 308 existing tests still pass.

---

- [ ] 3. Create the `ApiResponse` trait

  All API controllers will share a consistent JSON envelope. Centralise it in a trait so controllers stay thin.

  **What to do:** Create `app/Http/Controllers/Api/ApiResponse.php` with namespace `App\Http\Controllers\Api` as a `trait ApiResponse`:

  ```php
  trait ApiResponse
  {
      protected function success(mixed $data = null, string $message = '', int $code = 200): \Illuminate\Http\JsonResponse
      {
          return response()->json([
              'success' => true,
              'message' => $message,
              'data'    => $data,
          ], $code);
      }

      protected function error(string $message, int $code = 400, array $errors = []): \Illuminate\Http\JsonResponse
      {
          $payload = ['success' => false, 'message' => $message];
          if (! empty($errors)) {
              $payload['errors'] = $errors;
          }
          return response()->json($payload, $code);
      }
  }
  ```

  **Files to create:**
  - `app/Http/Controllers/Api/ApiResponse.php`

  **Verify:** `php artisan route:list` exits without error (no syntax check needed yet — class just needs to load). Confirmed at the test-run in step 6.

---

- [ ] 4. Create the API Resources

  Resources transform Eloquent models to the JSON shape the API contract specifies. Create all six in `app/Http/Resources/`. Follow the existing model attribute/cast names exactly (read from the models in steps above).

  **What to do:** Create each file as a class extending `Illuminate\Http\Resources\Json\JsonResource`.

  **4a. `OrganizationResource`** — fields: `id`, `name`, `type` (value + label from `OrganizationType::label()`), `code`, `depth`, `parent_id`, `is_active`.

  **4b. `UserResource`** — fields: `id`, `name`, `email`, `is_active`, `organization` (nested `OrganizationResource::make($this->whenLoaded('organization'))`).

  **4c. `LeaveTypeResource`** — fields: `id`, `code`, `name`, `deducts_balance`, `yearly_entitlement`, `max_days_per_request`, `is_active`.

  **4d. `EmployeeResource`** — fields: `id`, `employee_code`, `full_name`, `job_title`, `grade`, `entitlement_grade` (value + label from `EntitlementGrade::label()`), `birth_date`, `hire_date`, `work_start_date`, `phone`, `is_active`, `organization_id`, `user_id`.

  **4e. `LeaveBalanceResource`** — fields: `id`, `employee_id`, `leave_type` (nested `LeaveTypeResource::make($this->whenLoaded('leaveType'))`), `year`, `entitled`, `carried_over`, `used`, `remaining` (computed property already exists on model: `$this->remaining`).

  **4f. `LeaveRequestStepResource`** — fields: `id`, `step_order`, `stage`, `status` (value + label from `StepStatus`), `acted_by` (nested `UserResource::make($this->whenLoaded('actedBy'))`), `acted_at`, `note`.

  **4g. `LeaveRequestResource`** — fields: `id`, `number`, `employee` (nested `EmployeeResource`), `organization_id`, `leave_type` (nested `LeaveTypeResource`), `substitute_employee` (nested `EmployeeResource::make($this->whenLoaded('substituteEmployee'))`), `start_date`, `end_date`, `days`, `written_at`, `reason`, `status` (object with `value`, `label`, `color` — sourced from `LeaveStatus::label()` and `LeaveStatus::color()`), `current_stage`, `rejection_reason`, `balance_snapshot` (object with `entitled`, `used`, `remaining` — null when all three are null on the model), `steps` (nested `LeaveRequestStepResource::collection($this->whenLoaded('steps'))`), `created_by` (nested `UserResource`), `submitted_at`, `decided_at`, `can_cancel` (from model accessor), `can_edit` (from model accessor), `created_at`.

  **Files to create:**
  - `app/Http/Resources/OrganizationResource.php`
  - `app/Http/Resources/UserResource.php`
  - `app/Http/Resources/LeaveTypeResource.php`
  - `app/Http/Resources/EmployeeResource.php`
  - `app/Http/Resources/LeaveBalanceResource.php`
  - `app/Http/Resources/LeaveRequestStepResource.php`
  - `app/Http/Resources/LeaveRequestResource.php`

  **Verify:** `php artisan route:list` exits cleanly (all classes load). Pest run at step 6 will catch any breakage.

---

- [ ] 5. Create the FormRequests (app/Http/Requests/Api/)

  All requests extend `Illuminate\Foundation\Http\FormRequest`, use Arabic `messages()`, and `authorize()` returns `true` (authorization is handled via policies in controllers).

  **5a. `LoginRequest`**
  - `rules()`: `email` required|email, `password` required|string|min:8
  - `messages()`: Arabic messages for all rules

  **5b. `StoreLeaveRequestRequest`**
  - `rules()`:
    - `employee_id`: required|integer|exists:employees,id
    - `leave_type_id`: required|integer|exists:leave_types,id
    - `start_date`: required|date|after_or_equal:today
    - `end_date`: required|date|after_or_equal:start_date
    - `days`: nullable|numeric|min:0.5
    - `reason`: nullable|string|max:500
    - `written_at`: nullable|date
    - `substitute_employee_id`: nullable|integer|exists:employees,id
  - `messages()`: Arabic

  **5c. `UpdateLeaveRequestRequest`**
  - Same rules as `StoreLeaveRequestRequest` except `employee_id` and `leave_type_id` become `sometimes|required`.
  - `withValidator(Validator $validator)`: adds a `->after()` callback that checks `$this->route('leave_request')->status->canBeEdited()` and calls `$validator->errors()->add('status', 'لا يمكن تعديل الطلب في حالته الحالية.')` if not.

  **5d. `ApproveLeaveRequestRequest`**
  - `rules()`: `note` nullable|string|max:500
  - `messages()`: Arabic

  **5e. `RejectLeaveRequestRequest`**
  - `rules()`: `reason` required|string|min:3|max:500
  - `messages()`: Arabic (سبب الرفض مطلوب)

  **5f. `ReturnLeaveRequestRequest`**
  - `rules()`: `note` required|string|min:3|max:500
  - `messages()`: Arabic

  **5g. `StoreEmployeeRequest`**
  - `rules()`:
    - `employee_code`: required|string|max:50|unique:employees,employee_code
    - `full_name`: required|string|max:150
    - `job_title`: nullable|string|max:150
    - `grade`: nullable|string|max:100
    - `entitlement_grade`: nullable|string|in:\App\Enums\EntitlementGrade values (use `array_column(EntitlementGrade::cases(), 'value')`)
    - `birth_date`: nullable|date
    - `hire_date`: nullable|date
    - `work_start_date`: nullable|date
    - `phone`: nullable|string|max:20
    - `is_active`: boolean
    - `organization_id`: required|integer|exists:organizations,id
    - `user_id`: nullable|integer|exists:users,id
  - `messages()`: Arabic

  **5h. `UpdateEmployeeRequest`**
  - Same as `StoreEmployeeRequest` except `employee_code` uniqueness rule ignores current employee: `unique:employees,employee_code,{$this->route('employee')->id}`.
  - All fields `sometimes|required` (partial update).

  **Files to create:**
  - `app/Http/Requests/Api/LoginRequest.php`
  - `app/Http/Requests/Api/StoreLeaveRequestRequest.php`
  - `app/Http/Requests/Api/UpdateLeaveRequestRequest.php`
  - `app/Http/Requests/Api/ApproveLeaveRequestRequest.php`
  - `app/Http/Requests/Api/RejectLeaveRequestRequest.php`
  - `app/Http/Requests/Api/ReturnLeaveRequestRequest.php`
  - `app/Http/Requests/Api/StoreEmployeeRequest.php`
  - `app/Http/Requests/Api/UpdateEmployeeRequest.php`

  **Verify:** `php artisan route:list` (all classes must parse). Pest run at step 6 will also exercise these.

---

- [ ] 6. Create the API controllers and `routes/api.php`

  Create five controllers in `app/Http/Controllers/Api/`, each using `ApiResponse` trait, constructor-injecting the relevant service via DI. Then define all routes in `routes/api.php`.

  ### 6a. `AuthController`

  File: `app/Http/Controllers/Api/AuthController.php`
  Namespace: `App\Http\Controllers\Api`
  Uses: `ApiResponse`, injects nothing in constructor (uses `Auth::attempt`, `request()->user()`)

  Methods:
  - `login(LoginRequest $request): JsonResponse`
    1. Attempt `Auth::attempt(['email' => $request->email, 'password' => $request->password])`.
    2. On failure → `$this->error('بيانات الدخول غير صحيحة.', 401)`.
    3. On success → fetch user with `organization` loaded, create Sanctum token via `$user->createToken('api-token')->plainTextToken`, return `$this->success(['token' => $token, 'user' => UserResource::make($user)->toArray($request)], 'تم تسجيل الدخول بنجاح.')`.
    4. `UserResource` must include the nested `organization` (with `id`, `name`, `type`) — `organization` is already `whenLoaded` in the resource, so eager-load it before passing.

  - `logout(Request $request): JsonResponse`
    1. `$request->user()->currentAccessToken()->delete()`.
    2. Return `$this->success(null, 'تم تسجيل الخروج بنجاح.')`.

  - `me(Request $request): JsonResponse`
    1. Load `$request->user()->load('organization')`.
    2. Return `$this->success(UserResource::make($request->user()))`.

  ### 6b. `LeaveRequestController`

  File: `app/Http/Controllers/Api/LeaveRequestController.php`
  Constructor: injects `LeaveRequestService $service`.

  Methods:
  - `index(Request $request): JsonResponse`
    - Build query on `LeaveRequest::with(['employee', 'leaveType', 'steps'])`.
    - Apply optional filters from query string: `status` (string match against `LeaveStatus` value), `employee_id` (integer), `leave_type_id` (integer), `from`/`to` (date range on `start_date`).
    - Paginate with `paginate(15)`.
    - Authorize `viewAny` via `$this->authorize('viewAny', LeaveRequest::class)`.
    - Return `$this->success(LeaveRequestResource::collection($paginated))`.

  - `store(StoreLeaveRequestRequest $request): JsonResponse`
    - Authorize `create` via `$this->authorize('create', LeaveRequest::class)`.
    - Call `$this->service->create($request->validated(), $request->user())`.
    - Return `$this->success(LeaveRequestResource::make($result->request), 'تم إنشاء الطلب بنجاح.', 201)` with optional warnings in the envelope (add `'warnings' => $result->warnings` to the data array if `$result->hasWarnings()`).

  - `show(LeaveRequest $leaveRequest): JsonResponse`
    - Load `$leaveRequest->load(['employee', 'leaveType', 'steps.actedBy', 'createdBy', 'substituteEmployee'])`.
    - Authorize `view`.
    - Return `$this->success(LeaveRequestResource::make($leaveRequest))`.

  - `update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `update`.
    - Update directly on the model (no service method needed — service `create` is for new records; the request FormRequest already validates `canBeEdited`).
    - Call `$leaveRequest->update($request->validated())` (only `start_date`, `end_date`, `days`, `reason`, `written_at`, `substitute_employee_id` are updatable via this endpoint).
    - Return `$this->success(LeaveRequestResource::make($leaveRequest->fresh()), 'تم تحديث الطلب.')`.

  - `submit(Request $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `submit`.
    - Call `$this->service->submit($leaveRequest, $request->user())`.
    - Return `$this->success(LeaveRequestResource::make($updated), 'تم تقديم الطلب.')`.

  - `approve(ApproveLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `approve`.
    - Resolve the current pending step: `$step = $leaveRequest->steps()->where('stage', $leaveRequest->current_stage)->where('status', StepStatus::PENDING->value)->firstOrFail()`.
    - Call `$this->service->approve($leaveRequest, $step, $request->user(), $request->note)`.
    - Return `$this->success(LeaveRequestResource::make($updated), 'تم الاعتماد بنجاح.')`.

  - `reject(RejectLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `reject`.
    - Resolve current pending step (same as approve).
    - Call `$this->service->reject($leaveRequest, $step, $request->user(), $request->reason)`.
    - Return `$this->success(LeaveRequestResource::make($updated), 'تم الرفض.')`.

  - `return(ReturnLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `return`.
    - Resolve current pending step.
    - Call `$this->service->returnRequest($leaveRequest, $step, $request->user(), $request->note)`.
    - Return `$this->success(LeaveRequestResource::make($updated), 'تمت الإعادة للتعديل.')`.

  - `cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse`
    - Authorize `cancel`.
    - Call `$this->service->cancel($leaveRequest, $request->user())`.
    - Return `$this->success(LeaveRequestResource::make($updated), 'تم إلغاء الطلب.')`.

  ### 6c. `EmployeeController`

  File: `app/Http/Controllers/Api/EmployeeController.php`
  Constructor: no service needed (direct Eloquent + Policy).

  Methods:
  - `index(Request $request): JsonResponse` — `authorize('viewAny', Employee::class)`, query `Employee::active()->with('organization')->paginate(20)`, return `EmployeeResource::collection`.
  - `store(StoreEmployeeRequest $request): JsonResponse` — `authorize('create', Employee::class)`, `Employee::create($request->validated())`, return 201.
  - `show(Employee $employee): JsonResponse` — `authorize('view', $employee)`, load `organization`, return `EmployeeResource`.
  - `update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse` — `authorize('update', $employee)`, `$employee->update($request->validated())`, return `EmployeeResource`.
  - `destroy(Employee $employee): JsonResponse` — `authorize('delete', $employee)`, `$employee->delete()`, return `$this->success(null, 'تم الحذف.')`.
  - `balances(Employee $employee): JsonResponse` — `authorize('view', $employee)`, load `$employee->leaveBalances()->with('leaveType')->currentYear()->get()`, return `LeaveBalanceResource::collection`.

  ### 6d. `LeaveTypeController`

  File: `app/Http/Controllers/Api/LeaveTypeController.php`
  Constructor: no injection.

  Methods:
  - `index(): JsonResponse` — `LeaveType::active()->get()`, return `LeaveTypeResource::collection`.
  - `show(LeaveType $leaveType): JsonResponse` — return `LeaveTypeResource::make($leaveType)`.

  (Read-only — no policy gates needed; leave types are global reference data.)

  ### 6e. `OrganizationController`

  File: `app/Http/Controllers/Api/OrganizationController.php`
  Constructor: no injection. `OrganizationScope` fires automatically from `Auth::check()`.

  Methods:
  - `index(Request $request): JsonResponse` — `Organization::active()->paginate(20)`, return `OrganizationResource::collection`. Scope limits to user's subtree automatically.
  - `show(Organization $organization): JsonResponse` — return `OrganizationResource::make($organization)`.

  ### 6f. `DashboardController`

  File: `app/Http/Controllers/Api/DashboardController.php`
  Constructor: injects `DashboardStatsService $stats`.

  Methods:
  - `stats(Request $request): JsonResponse`
    1. Get `$user = $request->user()`, load `$org = $user->organization` with `withoutGlobalScopes()` if needed.
    2. If `$org === null` → return `$this->error('لا توجد مؤسسة مرتبطة بحسابك.', 422)`.
    3. Branch by `OrganizationType`:
       - `SCHOOL` → return `['status_counts' => $this->stats->schoolStatusCounts($org), 'on_leave_today' => EmployeeResource::collection($this->stats->onLeaveToday($org)->pluck('employee')), 'pending_requests' => LeaveRequestResource::collection($this->stats->pendingRequests($org)), 'overdue_count' => $this->stats->overdueCount($org)]`.
       - `ADMINISTRATION` → return `['school_stats' => $this->stats->administrationSchoolStats($org), 'top_leave_takers' => $this->stats->topLeaveTakers($org), 'avg_response_hours' => $this->stats->avgResponseTime($org), 'overdue_count' => $this->stats->overdueCount($org)]`.
       - `DIRECTORATE` → return `['admin_stats' => $this->stats->directorateAdminStats($org), 'monthly_trend' => $this->stats->monthlyRequestTrend($org, now()->year), 'yearly_comparison' => $this->stats->yearlyComparison($org), 'overdue_count' => $this->stats->overdueCount($org)]`.

  ### 6g. Routes — `routes/api.php`

  ```php
  <?php

  use App\Http\Controllers\Api\AuthController;
  use App\Http\Controllers\Api\LeaveRequestController;
  use App\Http\Controllers\Api\EmployeeController;
  use App\Http\Controllers\Api\LeaveTypeController;
  use App\Http\Controllers\Api\OrganizationController;
  use App\Http\Controllers\Api\DashboardController;
  use Illuminate\Support\Facades\Route;

  // Public
  Route::post('/auth/login', [AuthController::class, 'login']);

  // Protected
  Route::middleware('auth:sanctum')->group(function () {
      Route::post('/auth/logout', [AuthController::class, 'logout']);
      Route::get('/auth/me',     [AuthController::class, 'me']);

      Route::apiResource('leave-requests', LeaveRequestController::class)
          ->except(['destroy']);
      Route::post('/leave-requests/{leave_request}/submit',  [LeaveRequestController::class, 'submit']);
      Route::post('/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
      Route::post('/leave-requests/{leave_request}/reject',  [LeaveRequestController::class, 'reject']);
      Route::post('/leave-requests/{leave_request}/return',  [LeaveRequestController::class, 'return']);
      Route::post('/leave-requests/{leave_request}/cancel',  [LeaveRequestController::class, 'cancel']);

      Route::apiResource('employees', EmployeeController::class);
      Route::get('/employees/{employee}/balances', [EmployeeController::class, 'balances']);

      Route::apiResource('leave-types',    LeaveTypeController::class)->only(['index', 'show']);
      Route::apiResource('organizations',  OrganizationController::class)->only(['index', 'show']);

      Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
  });
  ```

  **Files to create/modify:**
  - `app/Http/Controllers/Api/AuthController.php`
  - `app/Http/Controllers/Api/LeaveRequestController.php`
  - `app/Http/Controllers/Api/EmployeeController.php`
  - `app/Http/Controllers/Api/LeaveTypeController.php`
  - `app/Http/Controllers/Api/OrganizationController.php`
  - `app/Http/Controllers/Api/DashboardController.php`
  - `routes/api.php`

  **Verify:** `php artisan route:list --path=api` — all 20+ API routes appear. `php vendor/bin/pest` — all 308 existing tests still pass.

---

- [ ] 7. Add exception handling in `bootstrap/app.php`

  Laravel 11 handles exceptions in the `withExceptions` closure. The block is currently empty. Add handlers scoped to `api/*` requests only, to avoid changing web behavior.

  **What to do:** Modify the `withExceptions` closure in `bootstrap/app.php`:

  ```php
  ->withExceptions(function (Exceptions $exceptions) {
      // ---- ValidationException → 422 ----
      $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'success' => false,
                  'message' => 'بيانات الإدخال غير صحيحة.',
                  'errors'  => $e->errors(),
              ], 422);
          }
      });

      // ---- AuthenticationException → 401 ----
      $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'success' => false,
                  'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
              ], 401);
          }
      });

      // ---- ModelNotFoundException → 404 ----
      $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'success' => false,
                  'message' => 'السجل المطلوب غير موجود.',
              ], 404);
          }
      });

      // ---- LeaveRequestException → 422 ----
      $exceptions->render(function (\App\Exceptions\LeaveRequestException $e, $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'success' => false,
                  'message' => $e->getMessage(),
              ], 422);
          }
      });
  })
  ```

  **Files to modify:**
  - `bootstrap/app.php`

  **Verify:** `php vendor/bin/pest` — all 308 existing tests still pass. Web routes (`/verify/{number}`, `/leave-pdf/{number}`) are unaffected because handlers return `null` (fall through) for non-`api/*` requests.

---

- [ ] 8. Write feature tests in `tests/Feature/ApiTest.php`

  Add ~12 Pest test cases covering the API surface. Use the same `RefreshDatabase` + `createHierarchy()` helpers already in `tests/Pest.php`. All test cases must run in isolation.

  **Test setup helper** (private to this file, not global): `setupApiTest()` — calls `createHierarchy()`, creates a `LeaveType::factory()->regular()`, an `Employee::factory()->inOrganization($orgs['school'])`, a `User::factory()->inOrganization($orgs['school'])` with a known password, a `LeaveBalance` record, and a 3-stage `WorkflowConfiguration`. Returns all created objects.

  **Test cases:**

  1. **`it('login returns token and user with organization on valid credentials')`**
     — POST `/api/auth/login` with valid email/password → 200, `data.token` present, `data.user.organization.id` matches user's org.

  2. **`it('login returns 401 on wrong password')`**
     — POST `/api/auth/login` with wrong password → 401, `success` false.

  3. **`it('login returns 422 when email is missing')`**
     — POST `/api/auth/login` with no email → 422, `errors.email` present.

  4. **`it('me returns authenticated user')`**
     — GET `/api/auth/me` with Sanctum token → 200, `data.email` matches the logged-in user.

  5. **`it('logout invalidates token')`**
     — POST `/api/auth/logout` → 200; subsequent GET `/api/auth/me` with same token → 401.

  6. **`it('store leave request creates a draft and returns 201')`**
     — POST `/api/leave-requests` with valid payload → 201, `data.status.value === 'draft'`.

  7. **`it('store leave request returns 422 on missing employee_id')`**
     — POST `/api/leave-requests` without `employee_id` → 422, `errors.employee_id` present.

  8. **`it('submit transitions leave request to submitted')`**
     — Create a draft via service, POST `/api/leave-requests/{id}/submit` → 200, `data.status.value === 'submitted'`.

  9. **`it('approve advances leave request stage')`**
     — Create a submitted request, POST `/api/leave-requests/{id}/approve` with `approve_leave_request` permission → 200, `data.status.value` in `['in_review', 'approved']`.

  10. **`it('reject sets status to rejected with reason')`**
      — POST `/api/leave-requests/{id}/reject` with `{ "reason": "ضغط العمل" }` → 200, `data.status.value === 'rejected'`.

  11. **`it('employee balances returns current year balances')`**
      — GET `/api/employees/{id}/balances` → 200, response is array, first item has `year` equal to `now()->year`.

  12. **`it('dashboard stats returns school stats for a school user')`**
      — GET `/api/dashboard/stats` → 200, `data.status_counts` key present.

  13. **`it('unauthenticated request returns 401')`**
      — GET `/api/leave-requests` without token → 401, `success` false.

  **Implementation notes:**
  - Authenticate in tests using `Sanctum::actingAs($user)` (from `Laravel\Sanctum\Sanctum`) — no need for actual token creation in tests.
  - For permission-gated tests (approve, reject), assign the relevant Spatie permission to the test user before acting: `$user->givePermissionTo('approve_leave_request')` after calling `setPermissionsTeamId($org->id)`.

  **Files to create:**
  - `tests/Feature/ApiTest.php`

  **Verify:** `php vendor/bin/pest tests/Feature/ApiTest.php` — all 13 new tests pass. Then `php vendor/bin/pest` — all 308 + 13 = 321 tests pass.

---

## Dependency order summary

```
Step 1 (sanctum install)
  → Step 2 (HasApiTokens on User)
    → Step 3 (ApiResponse trait)
      → Step 4 (Resources)
        → Step 5 (FormRequests)
          → Step 6 (Controllers + routes)
            → Step 7 (Exception handling)
              → Step 8 (Feature tests)
```

Steps 3, 4, and 5 are internally independent of each other (all depend only on steps 1–2) and can be written in parallel, but must all be complete before step 6.

---

## Assumptions and flags

- **Assumption — `routes/api.php` not yet registered.** The current `bootstrap/app.php` has no `api:` key in `withRouting`. Step 1 adds it. If this causes unexpected behavior with Filament (which uses web routes only), no impact is expected since Filament registers its own panel routes independently.

- **Assumption — `OrganizationScope` on API.** Because `OrganizationScope::apply` checks `Auth::check()`, it fires automatically for Sanctum-authenticated users on api routes, which is the correct behavior — no extra work needed.

- **Assumption — `DashboardController::stats` `onLeaveToday`.** `DashboardStatsService::onLeaveToday` returns a `Collection` of `LeaveRequest` models (with `employee` loaded), not `Employee` models. The controller should return `LeaveRequestResource::collection($this->stats->onLeaveToday($org))` directly rather than plucking employees.

- **No new migrations needed** beyond the Sanctum `personal_access_tokens` table.

- **`php artisan route:list` deprecation warnings** (`PDO::MYSQL_ATTR_SSL_CA`) are from PHP 8.5 and are cosmetic — they do not affect functionality or tests.
