<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'step_id' => ['required', 'integer', 'exists:leave_request_steps,id'],
            'note'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'step_id.required' => 'معرّف المرحلة مطلوب.',
            'step_id.exists'   => 'المرحلة المحددة غير موجودة.',
            'note.max'         => 'الملاحظة يجب ألا تتجاوز 500 حرف.',
        ];
    }
}
