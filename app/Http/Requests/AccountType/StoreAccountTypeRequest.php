<?php

namespace App\Http\Requests\AccountType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('account_types', 'name')->whereNull('deleted_at')],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم النوع', 'icon' => 'الأيقونة'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'نوع الحساب موجود مسبقاً.'];
    }
}
