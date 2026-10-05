<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Response;

class LeaveVerificationController extends Controller
{
    /**
     * Display the public verification page for a leave request.
     */
    public function show(string $number): Response
    {
        $request = LeaveRequest::withoutGlobalScopes()
            ->where('number', $number)
            ->with(['employee', 'leaveType', 'organization'])
            ->first();

        if (! $request) {
            abort(404);
        }

        return response()->view('public.leave-verify', compact('request'));
    }
}
