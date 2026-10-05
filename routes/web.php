<?php

use App\Http\Controllers\LeaveVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public verification page (no auth required)
Route::get('/verify/{number}', [LeaveVerificationController::class, 'show'])
    ->name('leave.verify');

// Authenticated PDF download route
Route::get('/leave-pdf/{number}', function (string $number) {
    $leaveRequest = \App\Models\LeaveRequest::withoutGlobalScopes()
        ->where('number', $number)
        ->firstOrFail();

    // Enforce per-record authorization — only users with 'view' permission on this record
    \Illuminate\Support\Facades\Gate::authorize('view', $leaveRequest);

    $content = app(\App\Services\LeaveRequestPdfService::class)->generate($leaveRequest);
    return response()->streamDownload(
        fn() => print($content),
        "leave-request-{$number}.pdf",
        ['Content-Type' => 'application/pdf']
    );
})->middleware('auth')->name('leave.pdf.download');

// Authenticated Excel export route
Route::get('/leave-export', function (\Illuminate\Http\Request $request) {
    $filters = $request->only(['status', 'organization_id', 'employee_name', 'leave_type_id']);
    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\LeaveRequestsExport($filters),
        'leave-requests.xlsx'
    );
})->middleware('auth')->name('leave.export');

// Authenticated report PDF download route
Route::get('/report-pdf', function (\Illuminate\Http\Request $request) {
    $filters = $request->only(['status', 'organization_id', 'leave_type_id', 'from', 'to']);
    $content = app(\App\Services\ReportPdfService::class)->generateSummary($filters);
    return response()->streamDownload(
        fn () => print($content),
        'report.pdf',
        ['Content-Type' => 'application/pdf']
    );
})->middleware('auth')->name('report.pdf.download');
