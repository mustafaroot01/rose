<?php

namespace App\Http\Requests\Governorate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGovernorateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('governorate')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('governorates', 'name')->ignore($id)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم المحافظة',
            'code' => 'الرمز',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم المحافظة موجود مسبقاً.',
        ];
    }
}
