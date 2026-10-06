<?php

namespace App\Http\Requests\Api;

use App\Enums\EntitlementGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'organization_id'   => ['required', 'integer', 'exists:organizations,id'],
            'employee_code'     => ['required', 'string', 'max:30', 'unique:employees,employee_code'],
            'full_name'         => ['required', 'string', 'max:255'],
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
            'organization_id.required'  => 'المؤسسة مطلوبة.',
            'organization_id.exists'    => 'المؤسسة المحددة غير موجودة.',
            'employee_code.required'    => 'كود الموظف مطلوب.',
            'employee_code.max'         => 'كود الموظف يجب ألا يتجاوز 30 حرفاً.',
            'employee_code.unique'      => 'كود الموظف مستخدم بالفعل.',
            'full_name.required'        => 'اسم الموظف مطلوب.',
            'full_name.max'             => 'اسم الموظف يجب ألا يتجاوز 255 حرفاً.',
            'job_title.max'             => 'المسمى الوظيفي يجب ألا يتجاوز 100 حرف.',
            'entitlement_grade.enum'    => 'الدرجة الوظيفية المحددة غير صحيحة.',
            'birth_date.date'           => 'تاريخ الميلاد غير صحيح.',
            'birth_date.before'         => 'تاريخ الميلاد يجب أن يكون في الماضي.',
            'hire_date.date'            => 'تاريخ التعيين غير صحيح.',
            'work_start_date.date'      => 'تاريخ استلام العمل غير صحيح.',
            'phone.max'                 => 'رقم الهاتف يجب ألا يتجاوز 20 رقماً.',
        ];
    }
}
