<?php

namespace App\Http\Requests\AccountType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('account_type')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('account_types', 'name')->ignore($id)->whereNull('deleted_at')],
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
