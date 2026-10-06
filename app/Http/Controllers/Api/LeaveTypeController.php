<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;

class LeaveTypeController extends Controller
{
    use ApiResponse;

    // GET /api/leave-types
    public function index(): JsonResponse
    {
        $types = LeaveType::active()->get();
        return $this->success(LeaveTypeResource::collection($types));
    }

    // GET /api/leave-types/{id}
    public function show(LeaveType $leaveType): JsonResponse
    {
        return $this->success(LeaveTypeResource::make($leaveType));
    }
}
