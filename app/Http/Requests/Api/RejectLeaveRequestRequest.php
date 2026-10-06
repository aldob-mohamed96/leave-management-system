<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'step_id' => ['required', 'integer', 'exists:leave_request_steps,id'],
            'reason'  => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'step_id.required' => 'معرّف المرحلة مطلوب.',
            'step_id.exists'   => 'المرحلة المحددة غير موجودة.',
            'reason.required'  => 'سبب الرفض مطلوب ولا يمكن أن يكون فارغاً.',
            'reason.min'       => 'سبب الرفض مطلوب ولا يقل عن 5 أحرف.',
            'reason.max'       => 'سبب الرفض يجب ألا يتجاوز 1000 حرف.',
        ];
    }
}
