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
