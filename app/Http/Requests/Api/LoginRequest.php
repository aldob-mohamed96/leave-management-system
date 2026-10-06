<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accept either "email" (legacy) or "login" (email or phone)
            'login'    => ['required_without:email', 'string', 'max:255'],
            'email'    => ['required_without:login', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required_without'  => 'البريد الإلكتروني أو رقم التليفون مطلوب.',
            'email.required_without'  => 'البريد الإلكتروني أو رقم التليفون مطلوب.',
            'password.required'       => 'كلمة المرور مطلوبة.',
            'password.string'         => 'كلمة المرور يجب أن تكون نصاً.',
            'password.min'            => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
        ];
    }

    public function loginIdentifier(): string
    {
        return (string) ($this->input('login') ?: $this->input('email'));
    }
}
