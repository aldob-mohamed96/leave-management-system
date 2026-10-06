<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LeaveRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApproveLeaveRequestRequest;
use App\Http\Requests\Api\RejectLeaveRequestRequest;
use App\Http\Requests\Api\ReturnLeaveRequestRequest;
use App\Http\Requests\Api\StoreLeaveRequestRequest;
use App\Http\Requests\Api\UpdateLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestStep;
use App\Rules\Leave\CasualBeforeRegularRule;
use App\Rules\Leave\MaxDaysPerRequestRule;
use App\Rules\Leave\NoOverlapRule;
use App\Rules\Leave\SufficientBalanceRule;
use App\Rules\Leave\WorkingDaysRule;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LeaveRequestService $service,
    ) {}

    // GET /api/leave-requests
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employee', 'leaveType', 'organization'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($ltId = $request->query('leave_type_id')) {
            $query->where('leave_type_id', $ltId);
        }
        if ($name = $request->query('employee_name')) {
            $query->whereHas('employee', fn($q) => $q->where('full_name', 'like', "%{$name}%"));
        }
        if ($from = $request->query('start_date')) {
            $query->where('start_date', '>=', $from);
        }
        if ($to = $request->query('end_date')) {
            $query->where('end_date', '<=', $to);
        }

        $paginated = $query->paginate(15);

        return $this->paginated(
            LeaveRequestResource::collection($paginated)->response()->getData(),
            null
        );
    }

    // POST /api/leave-requests
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        try {
            $result = $this->service->create($request->validated(), $request->user());
            return $this->success(
                LeaveRequestResource::make($result->request->load(['employee', 'leaveType', 'organization'])),
                'تم إنشاء طلب الإجازة بنجاح.' . ($result->hasWarnings() ? ' تحذيرات: ' . implode(' | ', $result->warnings) : ''),
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('بيانات غير صحيحة.', 422, $e->errors());
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // GET /api/leave-requests/{id}
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->load(['employee.organization', 'leaveType', 'organization', 'steps.actedBy', 'attachments']);
        return $this->success(LeaveRequestResource::make($leaveRequest));
    }

    // PUT /api/leave-requests/{id}
    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $data = array_filter($request->validated(), fn($v) => $v !== null);

        // Recalculate working days if dates changed
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $mergedData = array_merge([
                'start_date' => $leaveRequest->start_date->toDateString(),
                'end_date'   => $leaveRequest->end_date->toDateString(),
                'days'       => $leaveRequest->days,
            ], $data);

            $rule = new WorkingDaysRule();
            $rule->setData($mergedData);
            $rule->validate('days', $mergedData['days'], fn($msg) => null);
            if ($rule->calculatedDays > 0) {
                $data['days'] = $rule->calculatedDays;
            }
        }

        $leaveRequest->update($data);

        return $this->success(
            LeaveRequestResource::make($leaveRequest->fresh(['employee', 'leaveType', 'organization'])),
            'تم تحديث طلب الإجازة بنجاح.'
        );
    }

    // POST /api/leave-requests/{id}/submit
    public function submit(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $result = $this->service->submit($leaveRequest, $request->user());
            return $this->success(
                LeaveRequestResource::make($result->load(['employee', 'leaveType', 'steps'])),
                'تم تقديم الطلب بنجاح.'
            );
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // POST /api/leave-requests/{id}/approve
    public function approve(ApproveLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $step   = LeaveRequestStep::findOrFail($request->validated('step_id'));
            $result = $this->service->approve($leaveRequest, $step, $request->user(), $request->validated('note'));
            return $this->success(
                LeaveRequestResource::make($result->load(['employee', 'leaveType', 'steps.actedBy'])),
                'تمت الموافقة على المرحلة بنجاح.'
            );
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // POST /api/leave-requests/{id}/reject
    public function reject(RejectLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $step   = LeaveRequestStep::findOrFail($request->validated('step_id'));
            $result = $this->service->reject($leaveRequest, $step, $request->user(), $request->validated('reason'));
            return $this->success(
                LeaveRequestResource::make($result->load(['employee', 'leaveType', 'steps.actedBy'])),
                'تم رفض الطلب.'
            );
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // POST /api/leave-requests/{id}/return
    public function returnRequest(ReturnLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $step   = LeaveRequestStep::findOrFail($request->validated('step_id'));
            $result = $this->service->returnRequest($leaveRequest, $step, $request->user(), $request->validated('note'));
            return $this->success(
                LeaveRequestResource::make($result->load(['employee', 'leaveType', 'steps.actedBy'])),
                'تم إعادة الطلب للتعديل.'
            );
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // POST /api/leave-requests/{id}/cancel
    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $result = $this->service->cancel($leaveRequest, $request->user());
            return $this->success(
                LeaveRequestResource::make($result->load(['employee', 'leaveType'])),
                'تم إلغاء الطلب بنجاح.'
            );
        } catch (LeaveRequestException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // Helper for paginated response
    protected function paginated(mixed $resource, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $resource->data ?? $resource,
            'meta'    => $resource->meta ?? null,
        ]);
    }
}
