<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'supervisor'])],
            'account_type_id' => ['nullable', 'integer', Rule::exists('account_types', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')->whereNull('deleted_at')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
            'nahiya_id' => ['nullable', 'integer', Rule::exists('nahiyas', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'phone' => 'رقم الجوال',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'role' => 'الدور',
            'account_type_id' => 'نوع الحساب',
            'status' => 'الحالة',
        ];
    }
}
