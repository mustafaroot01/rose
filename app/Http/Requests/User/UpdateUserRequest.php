<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(['admin', 'supervisor', 'citizen'])],
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
            'email' => 'البريد الإلكتروني',
            'role' => 'الدور',
            'account_type_id' => 'نوع الحساب',
            'status' => 'الحالة',
        ];
    }
}
