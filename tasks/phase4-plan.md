# Implementation Plan — Phase 4

> Codebase facts established during exploration:
> - All required packages are **already installed**: `barryvdh/laravel-dompdf ^2.0`, `maatwebsite/excel ^3.1`, `simplesoftwareio/simple-qrcode ^4.0`.
> - `dompdf.php` config: `enable_remote => true` and `default_font => "serif"`. Both need updating.
> - `LeaveRequest::scopeOverdue()` **already exists** — no need to add it.
> - `LeaveRequestNotification::emailSubject()` has no `'overdue'` case — needs adding.
> - `LeaveRequestService` notify methods all call `$user->notify(new LeaveRequestNotification(...))` — correct for Filament bell; no change needed there.
> - No factories exist in `database/factories/` — tests must build models directly via `Model::create()` following the pattern in `tests/Pest.php`.
> - Test runner is **Pest 3** with `RefreshDatabase`. Run with `./vendor/bin/pest tests/Feature/Phase4Test.php`.
> - `EmployeeResource` has no header actions beyond `CreateAction`. The import button goes into `ListEmployees::getHeaderActions()`.
> - `LeaveRequestResource` table already has a bulk action stub named `export` with `fn() => null` — replace that stub.

---

- [ ] 1. **Update `config/dompdf.php`** — change two options so the PDF service works correctly.

  Set `"default_font" => "DejaVu Sans"` (supports Arabic glyphs via dompdf's bundled DejaVu font) and change `"enable_remote" => false` (disables remote HTTP fetching; the Blade template will use only inline SVG — no external resources).

  Files: `config/dompdf.php`

  Verify: `php artisan config:clear` exits without error. No test needed for this isolated config change; it is confirmed when the PDF service test passes in step 8.

---

- [ ] 2. **Add `'overdue'` case to `LeaveRequestNotification::emailSubject()`**

  Inside the `emailSubject()` private method, add a new match arm before `default`:

  ```php
  'overdue'  => "طلب إجازة متأخر يحتاج إجراءً — {$this->leaveRequest->number}",
  ```

  This is a one-line addition. No structural change to the class; `via()` and `toDatabase()` already handle any string event value.

  Files: `app/Notifications/LeaveRequestNotification.php`

  Verify: `./vendor/bin/pest tests/Feature/Phase4Test.php --filter overdue_notification` — relevant test passes (written in step 8).

---

- [ ] 3. **Create `app/Console/Commands/NotifyOverdueRequests.php`**

  Command class with:
  - `$signature = 'leave:notify-overdue {--days=3}'`
  - `$description = 'إرسال تنبيهات للطلبات المتأخرة التي لم يُتخذ فيها أي إجراء'`
  - `handle()` method:
    1. `$days = (int) $this->option('days');`
    2. Query: `LeaveRequest::withoutGlobalScopes()->overdue($days)->with(['createdBy', 'organization'])->get()`
    3. For each request: notify `$request->createdBy` (if not null) with `new LeaveRequestNotification($request, 'overdue', "طلب الإجازة رقم {$request->number} لم يُتخذ فيه إجراء منذ {$days} أيام")`
    4. Also notify users in the same `organization_id` who have the role `'مدير الإدارة'`:
       ```php
       setPermissionsTeamId($request->organization_id);
       $managers = \App\Models\User::where('organization_id', $request->organization_id)
           ->where('is_active', true)->get()
           ->filter(fn($u) => $u->hasRole('مدير الإدارة'));
       setPermissionsTeamId(null);
       foreach ($managers as $manager) {
           $manager->notify(new LeaveRequestNotification(...));
       }
       ```
    5. Wrap everything in a try/catch (\Throwable) so one bad notification doesn't abort the loop.
    6. `$this->info("تم إرسال التنبيهات لـ {$count} طلب متأخر.");`
  - The class is auto-discovered by Laravel 11's `bootstrap/app.php` — **no registration needed** in `routes/console.php`. (Laravel 11 auto-discovers commands in `app/Console/Commands`.)

  Files: `app/Console/Commands/NotifyOverdueRequests.php`

  Verify: `php artisan leave:notify-overdue --help` shows the command. Full notification test in step 8.

---

- [ ] 4. **Create `app/Http/Controllers/LeaveVerificationController.php`**

  Single `__invoke(string $number)` method:
  ```php
  $request = LeaveRequest::withoutGlobalScopes()
      ->where('number', $number)
      ->with(['employee', 'leaveType', 'organization'])
      ->first();

  if (! $request) {
      abort(404);
  }

  return view('public.leave-verify', compact('request'));
  ```

  No constructor injection needed. No middleware.

  Files: `app/Http/Controllers/LeaveVerificationController.php`

  Verify: Controller exists and the route is wired in step 5 — confirmed by the HTTP test in step 8.

---

- [ ] 5. **Register the public verification route in `routes/web.php`**

  Add after the existing `/` route:
  ```php
  Route::get('/verify/{number}', \App\Http\Controllers\LeaveVerificationController::class)
      ->name('leave.verify');
  ```

  No `auth` middleware — this is intentionally public.

  Files: `routes/web.php`

  Verify: `php artisan route:list --name=leave.verify` shows the route with URI `verify/{number}`.

---

- [ ] 6. **Create `resources/views/public/leave-verify.blade.php`**

  Simple Arabic HTML page (no Blade layout dependency — standalone):
  ```html
  <!DOCTYPE html>
  <html lang="ar" dir="rtl">
  <head>
      <meta charset="UTF-8">
      <title>التحقق من طلب الإجازة</title>
      <style>
          body { font-family: Arial, sans-serif; direction: rtl; max-width: 700px; margin: 40px auto; }
          .badge { display: inline-block; padding: 4px 12px; border-radius: 4px; }
          .verified { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; margin: 16px 0; padding: 12px; }
      </style>
  </head>
  <body>
      <h1>التحقق من طلب الإجازة</h1>
      <p class="verified">هذا الطلب تم التحقق منه إلكترونياً عبر نظام إدارة الإجازات</p>
      <table>
          <tr><th>رقم الطلب</th><td>{{ $request->number }}</td></tr>
          <tr><th>اسم الموظف</th><td>{{ $request->employee?->full_name ?? '—' }}</td></tr>
          <tr><th>نوع الإجازة</th><td>{{ $request->leaveType?->name ?? '—' }}</td></tr>
          <tr><th>من</th><td>{{ $request->start_date?->toDateString() }}</td></tr>
          <tr><th>إلى</th><td>{{ $request->end_date?->toDateString() }}</td></tr>
          <tr><th>عدد الأيام</th><td>{{ $request->days }}</td></tr>
          <tr><th>الحالة</th><td>{{ $request->status->label() }}</td></tr>
          <tr><th>تاريخ القرار</th><td>{{ $request->decided_at?->toDateString() ?? '—' }}</td></tr>
      </table>
      @if($request->status === \App\Enums\LeaveStatus::REJECTED && $request->rejection_reason)
          <p><strong>سبب الرفض:</strong> {{ $request->rejection_reason }}</p>
      @endif
  </body>
  </html>
  ```

  Files: `resources/views/public/leave-verify.blade.php`

  Verify: HTTP test in step 8 (GET /verify/{number} returns 200 with employee name visible in body).

---

- [ ] 7. **Create `resources/views/pdf/leave-request.blade.php`**

  Standalone HTML with **inline CSS only** (dompdf cannot load external stylesheets). Key layout points:
  - `<html dir="rtl">` with `<meta charset="UTF-8">`
  - Inline `<style>` block at top of `<body>` (dompdf 2.x has issues with `<head>` `<style>` blocks; put styles inside body wrapper div)
  - Font: `font-family: DejaVu Sans, serif;` on all elements (matches `default_font` set in step 1)
  - **Header section**: Ministry title + "طلب إجازة" + request number in an `<h1>` block
  - **Employee section**: table with full_name, employee_code, job_title, organization name, entitlement_grade label
  - **Leave section**: table with leave type, start_date, end_date, days, reason, written_at
  - **Opinions table**: three rows (manager / officer / admin), each with columns: الدور | الاسم | التاريخ | التوقيع (empty signature box `<td style="height:40px;border:1px solid #000;">&nbsp;</td>`)
  - **Rejection note section**: `@if($leaveRequest->rejection_reason)` block
  - **Footer**: QR code SVG embedded inline + issued date (`{{ now()->toDateString() }}`) + "صفحة 1"

  QR code embedding — **gotcha**: `simple-qrcode` returns an SVG string. Wrap it in a `<div>` and output raw: `{!! $qrCode !!}`. The service passes it pre-generated.

  Files: `resources/views/pdf/leave-request.blade.php`

  Verify: Confirmed functional when PDF service test in step 8 returns a non-empty string.

---

- [ ] 8. **Create `app/Services/LeaveRequestPdfService.php`**

  ```php
  namespace App\Services;

  use App\Models\LeaveRequest;
  use Barryvdh\DomPDF\Facade\Pdf;
  use SimpleSoftwareIO\QrCode\Facades\QrCode;

  class LeaveRequestPdfService
  {
      public function generate(LeaveRequest $request): string
      {
          $qrCode = QrCode::format('svg')
              ->size(80)
              ->generate(route('leave.verify', ['number' => $request->number]));

          $html = view('pdf.leave-request', [
              'leaveRequest' => $request,
              'qrCode'       => $qrCode,
          ])->render();

          $pdf = Pdf::loadHTML($html);

          return $pdf->output();
      }
  }
  ```

  **Gotcha**: Use `Pdf::loadHTML($rendered_string)` not `Pdf::loadView()` so the already-rendered SVG string is passed directly. `loadView()` works equally well but loadHTML avoids a double render. Either is acceptable — use `loadView` to keep it clean:
  ```php
  $pdf = Pdf::loadView('pdf.leave-request', [
      'leaveRequest' => $request,
      'qrCode'       => $qrCode,
  ]);
  return $pdf->output();
  ```

  Files: `app/Services/LeaveRequestPdfService.php`

  Verify: Unit/Feature test in step 12.

---

- [ ] 9. **Add the 'تحميل PDF' header action to `ViewLeaveRequest`**

  Add a new `Action` at the **end** of `getHeaderActions()` array (after the cancel action):
  ```php
  Action::make('downloadPdf')
      ->label('تحميل PDF')
      ->icon('heroicon-o-document-arrow-down')
      ->color('gray')
      ->action(function (): \Symfony\Component\HttpFoundation\Response {
          $record = $this->getRecord();
          $pdfContent = app(\App\Services\LeaveRequestPdfService::class)->generate($record);
          $filename = "leave-request-{$record->number}.pdf";

          return response()->streamDownload(
              fn() => print($pdfContent),
              $filename,
              ['Content-Type' => 'application/pdf']
          );
      }),
  ```

  **Gotcha**: Filament `Action::action()` callbacks cannot return a `Response` directly for downloads in the normal Livewire lifecycle. Use `$this->redirect()` to a dedicated download URL, OR register a `route` on the action. The correct Filament v3 pattern is to use `->url(route(...))` with `->openUrlInNewTab()`, pointing to a simple authenticated download route. Implement as:

  ```php
  Action::make('downloadPdf')
      ->label('تحميل PDF')
      ->icon('heroicon-o-document-arrow-down')
      ->color('gray')
      ->url(fn(): string => route('leave.pdf.download', ['number' => $this->getRecord()->number]))
      ->openUrlInNewTab(),
  ```

  And add a corresponding authenticated route in `routes/web.php`:
  ```php
  Route::get('/leave-pdf/{number}', function (string $number) {
      $leaveRequest = \App\Models\LeaveRequest::withoutGlobalScopes()
          ->where('number', $number)
          ->firstOrFail();
      $content = app(\App\Services\LeaveRequestPdfService::class)->generate($leaveRequest);
      return response()->streamDownload(
          fn() => print($content),
          "leave-request-{$number}.pdf",
          ['Content-Type' => 'application/pdf']
      );
  })->middleware('auth')->name('leave.pdf.download');
  ```

  Files:
  - `app/Filament/Resources/LeaveRequestResource/Pages/ViewLeaveRequest.php`
  - `routes/web.php`

  Verify: `php artisan route:list --name=leave.pdf.download` shows the route. Manual smoke-test in browser or via the feature test.

---

- [ ] 10. **Create `app/Exports/LeaveRequestsExport.php`**

  Implements: `FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`, `WithStyles` (all from `Maatwebsite\Excel\Concerns`).

  ```php
  namespace App\Exports;

  use App\Models\LeaveRequest;
  use Illuminate\Database\Eloquent\Builder;
  use Maatwebsite\Excel\Concerns\FromQuery;
  use Maatwebsite\Excel\Concerns\ShouldAutoSize;
  use Maatwebsite\Excel\Concerns\WithHeadings;
  use Maatwebsite\Excel\Concerns\WithMapping;
  use Maatwebsite\Excel\Concerns\WithStyles;
  use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

  class LeaveRequestsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
  {
      public function __construct(private array $filters = []) {}

      public function query(): Builder
      {
          return LeaveRequest::withoutGlobalScopes()
              ->with(['employee', 'organization', 'leaveType', 'createdBy'])
              ->when($this->filters['status'] ?? null,
                  fn($q, $v) => $q->where('status', $v))
              ->when($this->filters['organization_id'] ?? null,
                  fn($q, $v) => $q->where('organization_id', $v))
              ->when($this->filters['employee_name'] ?? null,
                  fn($q, $v) => $q->whereHas('employee', fn($eq) => $eq->where('full_name', 'like', "%{$v}%")))
              ->when($this->filters['leave_type_id'] ?? null,
                  fn($q, $v) => $q->where('leave_type_id', $v))
              ->when($this->filters['date_range']['from'] ?? null,
                  fn($q, $v) => $q->whereDate('start_date', '>=', $v))
              ->when($this->filters['date_range']['to'] ?? null,
                  fn($q, $v) => $q->whereDate('start_date', '<=', $v));
      }

      public function headings(): array
      {
          return [
              'رقم الطلب', 'اسم الموظف', 'كود الموظف', 'المدرسة',
              'نوع الإجازة', 'من', 'إلى', 'عدد الأيام',
              'الحالة', 'مقدم بواسطة', 'تاريخ التقديم', 'القرار',
          ];
      }

      public function map($row): array
      {
          return [
              $row->number,
              $row->employee?->full_name ?? '—',
              $row->employee?->employee_code ?? '—',
              $row->organization?->name ?? '—',
              $row->leaveType?->name ?? '—',
              $row->start_date?->toDateString(),
              $row->end_date?->toDateString(),
              $row->days,
              $row->status->label(),
              $row->createdBy?->name ?? '—',
              $row->submitted_at?->toDateString(),
              $row->decided_at?->toDateString() ?? '—',
          ];
      }

      public function styles(Worksheet $sheet): array
      {
          return [
              1 => [
                  'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                  'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']],
              ],
          ];
      }
  }
  ```

  Files: `app/Exports/LeaveRequestsExport.php`

  Verify: Export test in step 12.

---

- [ ] 11. **Wire the Excel export actions into `LeaveRequestResource` and `ListLeaveRequests`**

  Two changes:

  **A. Replace the existing bulk action stub** in `LeaveRequestResource::table()`. The current stub is:
  ```php
  Tables\Actions\BulkAction::make('export')
      ->label('تصدير')
      ->icon('heroicon-o-arrow-down-tray')
      ->action(fn() => null), // placeholder
  ```
  Replace the `->action(fn() => null)` with:
  ```php
  ->action(function (\Illuminate\Database\Eloquent\Collection $records): \Symfony\Component\HttpFoundation\BinaryFileResponse {
      $ids = $records->pluck('id')->toArray();
      return \Maatwebsite\Excel\Facades\Excel::download(
          new \App\Exports\LeaveRequestsExport(['ids' => $ids]),
          'leave-requests.xlsx'
      );
  }),
  ```
  Also add `'ids'` filter handling to `LeaveRequestsExport::query()`:
  ```php
  ->when($this->filters['ids'] ?? null,
      fn($q, $v) => $q->whereIn('id', $v))
  ```

  **B. Add a 'تصدير Excel' header action to `ListLeaveRequests::getHeaderActions()`**:
  ```php
  \Filament\Actions\Action::make('exportAll')
      ->label('تصدير Excel')
      ->icon('heroicon-o-arrow-down-tray')
      ->color('success')
      ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
          return \Maatwebsite\Excel\Facades\Excel::download(
              new \App\Exports\LeaveRequestsExport(),
              'leave-requests.xlsx'
          );
      }),
  ```

  **Gotcha**: Filament header actions that return a `Response` from `->action()` in Livewire won't trigger a file download automatically. Use `->url(route('leave.export'))` + `->openUrlInNewTab()` pattern, same as the PDF download. Register:
  ```php
  Route::get('/leave-export', function (\Illuminate\Http\Request $request) {
      $filters = $request->only(['status', 'organization_id', 'employee_name', 'leave_type_id']);
      return \Maatwebsite\Excel\Facades\Excel::download(
          new \App\Exports\LeaveRequestsExport($filters),
          'leave-requests.xlsx'
      );
  })->middleware('auth')->name('leave.export');
  ```
  The header action becomes `->url(route('leave.export'))->openUrlInNewTab()`.

  The bulk action on selected rows DOES work returning a download from `->action()` in Filament table bulk actions (they use a redirect approach internally), so keep the bulk action as a direct `->action()` return.

  Files:
  - `app/Filament/Resources/LeaveRequestResource.php`
  - `app/Filament/Resources/LeaveRequestResource/Pages/ListLeaveRequests.php`
  - `app/Exports/LeaveRequestsExport.php` (add `ids` filter)
  - `routes/web.php`

  Verify: `php artisan route:list --name=leave.export` shows the route.

---

- [ ] 12. **Create `app/Imports/EmployeesImport.php`**

  Implements: `ToModel`, `WithHeadings`, `WithValidation`, `WithBatchInserts`, `SkipsOnError`.

  ```php
  namespace App\Imports;

  use App\Models\Employee;
  use App\Models\Organization;
  use Illuminate\Support\Collection;
  use Maatwebsite\Excel\Concerns\SkipsOnError;
  use Maatwebsite\Excel\Concerns\ToModel;
  use Maatwebsite\Excel\Concerns\WithBatchInserts;
  use Maatwebsite\Excel\Concerns\WithHeadings;
  use Maatwebsite\Excel\Concerns\WithValidation;
  use Throwable;

  class EmployeesImport implements ToModel, WithHeadings, WithValidation, WithBatchInserts, SkipsOnError
  {
      private array $errors = [];

      public function model(array $row): ?Employee
      {
          $org = Organization::withoutGlobalScopes()
              ->where('code', $row['organization_code'])
              ->first();

          if (! $org) {
              return null; // skip — validation should catch this, but belt-and-suspenders
          }

          return new Employee([
              'organization_id'  => $org->id,
              'employee_code'    => $row['employee_code'],
              'full_name'        => $row['full_name'],
              'job_title'        => $row['job_title'] ?? null,
              'grade'            => $row['grade'] ?? null,
              'entitlement_grade'=> $row['entitlement_grade'] ?? null,
              'birth_date'       => $row['birth_date'] ?? null,
              'hire_date'        => $row['hire_date'] ?? null,
              'work_start_date'  => $row['work_start_date'] ?? null,
              'phone'            => $row['phone'] ?? null,
              'is_active'        => true,
          ]);
      }

      public function headings(): array
      {
          return [
              'employee_code', 'full_name', 'job_title', 'grade',
              'entitlement_grade', 'organization_code', 'birth_date',
              'hire_date', 'work_start_date', 'phone',
          ];
      }

      public function rules(): array
      {
          return [
              'employee_code'     => 'required|unique:employees,employee_code',
              'full_name'         => 'required|string',
              'organization_code' => 'required|exists:organizations,code',
          ];
      }

      public function batchSize(): int { return 100; }

      /** SkipsOnError requires this method */
      public function onError(Throwable $e): void
      {
          $this->errors[] = $e->getMessage();
      }

      public function getErrors(): array { return $this->errors; }
  }
  ```

  **Gotcha**: `SkipsOnError` requires implementing `onError(Throwable $e): void`. Without it the import will throw at runtime. Also note `WithHeadings` on an import class means the import skips the **first row** (treats it as the heading row) — the Excel file the admin uploads must have those column names in row 1.

  Files: `app/Imports/EmployeesImport.php`

  Verify: Import test in step 14.

---

- [ ] 13. **Add the 'استيراد موظفين' header action to `ListEmployees`**

  Add an `Action` to `ListEmployees::getHeaderActions()` after `CreateAction`:

  ```php
  \Filament\Actions\Action::make('importEmployees')
      ->label('استيراد موظفين')
      ->icon('heroicon-o-arrow-up-tray')
      ->color('info')
      ->form([
          \Filament\Forms\Components\FileUpload::make('file')
              ->label('ملف Excel / CSV')
              ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                   'text/csv', 'application/vnd.ms-excel'])
              ->required()
              ->disk('local')
              ->directory('imports/employees'),
      ])
      ->action(function (array $data): void {
          $path = storage_path('app/' . $data['file']);
          $import = new \App\Imports\EmployeesImport();
          \Maatwebsite\Excel\Facades\Excel::import($import, $path);

          $errors = $import->getErrors();
          $errorCount = count($errors);

          if ($errorCount === 0) {
              \Filament\Notifications\Notification::make()
                  ->success()
                  ->title('تم الاستيراد بنجاح')
                  ->send();
          } else {
              \Filament\Notifications\Notification::make()
                  ->warning()
                  ->title("تم الاستيراد مع {$errorCount} خطأ")
                  ->body(implode("\n", array_slice($errors, 0, 5)))
                  ->send();
          }
      }),
  ```

  **Gotcha**: Filament `FileUpload` with `->disk('local')` stores the file in `storage/app/`. The path returned by the form is relative to the disk root, so prefix with `storage_path('app/')` when passing to `Excel::import()`.

  Files: `app/Filament/Resources/EmployeeResource/Pages/ListEmployees.php`

  Verify: `php artisan filament:check` (or `php artisan about`) exits without error. Full import test in step 14.

---

- [ ] 14. **Create `tests/Feature/Phase4Test.php`**

  Use Pest syntax with `RefreshDatabase` (already configured in `tests/Pest.php` for the `Feature` directory). All model creation must use `Model::create()` directly (no factories exist). Set up authentication using `actingAs()`.

  Test cases to implement:

  **PDF service**
  ```php
  it('generates a non-empty PDF string for an approved request', function () {
      $org = createHierarchy()['school'];
      $employee = \App\Models\Employee::create([
          'organization_id' => $org->id,
          'full_name' => 'موظف تجريبي',
          'employee_code' => 'EMP-001',
          'is_active' => true,
      ]);
      $leaveType = \App\Models\LeaveType::create(['name' => 'إجازة اعتيادية', 'is_active' => true, 'deducts_balance' => false]);
      $request = \App\Models\LeaveRequest::create([
          'number' => 'LR-TEST-001',
          'employee_id' => $employee->id,
          'organization_id' => $org->id,
          'leave_type_id' => $leaveType->id,
          'start_date' => '2025-07-01',
          'end_date' => '2025-07-05',
          'days' => 5,
          'status' => \App\Enums\LeaveStatus::APPROVED,
          'created_by' => null,
      ]);

      $pdfContent = app(\App\Services\LeaveRequestPdfService::class)->generate($request);
      expect($pdfContent)->not->toBeEmpty();
      expect(substr($pdfContent, 0, 4))->toBe('%PDF');
  });
  ```

  **Public verification route**
  ```php
  it('returns 200 with employee name on GET /verify/{number}', function () {
      // ... create org, employee, leaveRequest same pattern as above
      $this->get(route('leave.verify', ['number' => 'LR-TEST-001']))
          ->assertOk()
          ->assertSee('موظف تجريبي');
  });

  it('returns 404 for an unknown leave request number', function () {
      $this->get(route('leave.verify', ['number' => 'INVALID-999']))
          ->assertNotFound();
  });
  ```

  **Excel export query**
  ```php
  it('export query returns correct rows filtered by status', function () {
      // create 2 approved + 1 rejected leave request
      // ...
      $export = new \App\Exports\LeaveRequestsExport(['status' => 'approved']);
      expect($export->query()->count())->toBe(2);
  });
  ```

  **Employee import**
  ```php
  it('valid rows create Employee records', function () {
      $org = createHierarchy()['school'];
      // Create a SpreadsheetFile in memory or use Maatwebsite\Excel\Testing\WithFaker pattern
      // Simplest: write a temp .csv, import it, check DB
      $csv = "employee_code,full_name,job_title,grade,entitlement_grade,organization_code,birth_date,hire_date,work_start_date,phone\n";
      $csv .= "EMP-IMP-01,أحمد محمد,معلم,,teacher,SCH-TEST,,,, \n";
      $path = sys_get_temp_dir() . '/test_import.csv';
      file_put_contents($path, $csv);

      $import = new \App\Imports\EmployeesImport();
      \Maatwebsite\Excel\Facades\Excel::import($import, $path);

      expect(\App\Models\Employee::withoutGlobalScopes()->where('employee_code', 'EMP-IMP-01')->exists())->toBeTrue();
  });

  it('invalid rows with bad organization code are skipped and collected', function () {
      $csv = "employee_code,full_name,job_title,grade,entitlement_grade,organization_code,birth_date,hire_date,work_start_date,phone\n";
      $csv .= "EMP-BAD-01,موظف سيء,معلم,,,BAD-ORG-CODE,,,, \n";
      $path = sys_get_temp_dir() . '/test_import_bad.csv';
      file_put_contents($path, $csv);

      $import = new \App\Imports\EmployeesImport();
      \Maatwebsite\Excel\Facades\Excel::import($import, $path);

      expect(\App\Models\Employee::withoutGlobalScopes()->where('employee_code', 'EMP-BAD-01')->exists())->toBeFalse();
  });
  ```

  **Overdue command**
  ```php
  it('leave:notify-overdue sends notifications to created_by users', function () {
      \Illuminate\Support\Facades\Notification::fake();

      $user = \App\Models\User::create([...]);
      // create a submitted request older than 1 day, created_by = $user->id, submitted_at = now()->subDays(2)
      // ...

      $this->artisan('leave:notify-overdue --days=1')->assertExitCode(0);

      \Illuminate\Support\Facades\Notification::assertSentTo(
          $user,
          \App\Notifications\LeaveRequestNotification::class,
          fn($n) => $n->event === 'overdue'
      );
  });
  ```

  Note on `\App\Models\User::create(...)` — inspect the User model's fillable fields before writing test data. The test must supply at minimum `name`, `email`, `password`.

  Files: `tests/Feature/Phase4Test.php`

  Verify: `./vendor/bin/pest tests/Feature/Phase4Test.php` — all tests pass.

---

## Dependency Order Summary

```
1. config/dompdf.php update          (no deps)
2. LeaveRequestNotification 'overdue' case (no deps)
3. NotifyOverdueRequests command     (depends on 2)
4. LeaveVerificationController       (no deps)
5. Route leave.verify                (depends on 4)
6. Blade: public/leave-verify.blade.php (depends on 5)
7. Blade: pdf/leave-request.blade.php  (depends on 5 for QR route)
8. LeaveRequestPdfService            (depends on 1, 7)
9. PDF download route + ViewLeaveRequest action (depends on 8)
10. LeaveRequestsExport              (no deps)
11. Export actions + route           (depends on 10)
12. EmployeesImport                  (no deps)
13. ListEmployees import action      (depends on 12)
14. Phase4Test                       (depends on all above)
```

## Known Gotchas / Edge Cases

| Area | Gotcha |
|------|--------|
| dompdf | **Inline CSS only** — no external `.css` files are loaded. Every style must be inside `<style>` tags in the template. |
| dompdf | **DejaVu Sans** is the only bundled font that covers Arabic Unicode. `default_font` must be changed from `"serif"` to `"DejaVu Sans"` (or specify `font-family: DejaVu Sans` inline). |
| QR code | `QrCode::format('svg')` returns an SVG string. Use `{!! $qrCode !!}` (unescaped) in Blade — not `{{ $qrCode }}`. |
| SkipsOnError | **Must implement `onError(Throwable $e): void`** or PHP will throw a fatal error on any import validation failure. |
| WithHeadings (import) | The import skips row 1 as headings. The uploaded Excel file must have column names in the first row matching the `headings()` array values exactly. |
| Filament file download | `Action::action()` callbacks **cannot return a `Response`** in Livewire Filament pages. Use `->url()->openUrlInNewTab()` pointing to an authenticated web route for both the PDF download and the header Excel export action. |
| OrganizationScope | `LeaveRequest` and `Employee` have `OrganizationScope` global scope. Always call `withoutGlobalScopes()` in the export query, the import, the verification controller, and the overdue command. |
| `leave.verify` route | Must be registered **before** the Filament panel routes. Since `routes/web.php` is loaded before Filament's route service provider, this is satisfied automatically. |
| `leave.pdf.download` route | Needs `->middleware('auth')` — it accesses the PDF service which calls `route('leave.verify', ...)`. The verify route itself is public so the generated URL is always valid. |
| Employee model `entitlement_grade` | Cast to `EntitlementGrade` enum. In the import, if the cell is blank or an unrecognised string, pass `null` rather than an empty string, or the cast will throw. |
