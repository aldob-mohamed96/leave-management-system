<?php

namespace App\Http\Requests\Api;

use App\Enums\EntitlementGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $employeeId = $this->route('employee');

        return [
            'organization_id'   => ['nullable', 'integer', 'exists:organizations,id'],
            'employee_code'     => ['nullable', 'string', 'max:30',
                                    "unique:employees,employee_code,{$employeeId}"],
            'full_name'         => ['nullable', 'string', 'max:255'],
            'job_title'         => ['nullable', 'string', 'max:100'],
            'grade'             => ['nullable', 'string', 'max:50'],
            'entitlement_grade' => ['nullable', new Enum(EntitlementGrade::class)],
            'birth_date'        => ['nullable', 'date', 'before:today'],
            'hire_date'         => ['nullable', 'date'],
            'work_start_date'   => ['nullable', 'date'],
            'phone'             => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.exists'    => 'المؤسسة المحددة غير موجودة.',
            'employee_code.max'         => 'كود الموظف يجب ألا يتجاوز 30 حرفاً.',
            'employee_code.unique'      => 'كود الموظف مستخدم بالفعل.',
            'full_name.max'             => 'اسم الموظف يجب ألا يتجاوز 255 حرفاً.',
            'entitlement_grade.enum'    => 'الدرجة الوظيفية المحددة غير صحيحة.',
            'birth_date.before'         => 'تاريخ الميلاد يجب أن يكون في الماضي.',
        ];
    }
}
