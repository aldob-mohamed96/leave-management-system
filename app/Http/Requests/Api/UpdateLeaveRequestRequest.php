<?php

namespace App\Http\Requests\Api;

use App\Enums\LeaveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id'            => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type_id'          => ['nullable', 'integer', 'exists:leave_types,id'],
            'start_date'             => ['nullable', 'date'],
            'end_date'               => ['nullable', 'date', 'after_or_equal:start_date'],
            'days'                   => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'written_at'             => ['nullable', 'date'],
            'reason'                 => ['nullable', 'string', 'max:500'],
            'substitute_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $request = \App\Models\LeaveRequest::withoutGlobalScopes()
                ->find($this->route('leave_request'));

            if (! $request) {
                $v->errors()->add('id', 'طلب الإجازة غير موجود.');
                return;
            }

            if (! $request->status->canBeEdited()) {
                $v->errors()->add('status',
                    "لا يمكن تعديل الطلب في حالته الحالية ({$request->status->label()})."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal'       => 'تاريخ النهاية يجب أن يكون بعد أو مساوياً لتاريخ البداية.',
            'days.min'                       => 'الحد الأدنى لعدد الأيام هو 0.5 يوم.',
            'days.max'                       => 'الحد الأقصى لعدد الأيام هو 365 يوماً.',
            'reason.max'                     => 'السبب يجب ألا يتجاوز 500 حرف.',
            'substitute_employee_id.exists'  => 'موظف الخلافة المحدد غير موجود.',
        ];
    }
}
