<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ReturnLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'step_id' => ['required', 'integer', 'exists:leave_request_steps,id'],
            'note'    => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'step_id.required' => 'معرّف المرحلة مطلوب.',
            'step_id.exists'   => 'المرحلة المحددة غير موجودة.',
            'note.required'    => 'ملاحظة الإعادة مطلوبة.',
            'note.min'         => 'ملاحظة الإعادة يجب ألا تقل عن 5 أحرف.',
            'note.max'         => 'ملاحظة الإعادة يجب ألا تتجاوز 500 حرف.',
        ];
    }
}
