<?php

namespace App\Http\Requests\District;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('districts', 'name')
                    ->where('governorate_id', $this->input('governorate_id'))
                    ->whereNull('deleted_at'),
            ],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'governorate_id' => 'المحافظة',
            'name' => 'اسم القضاء',
            'code' => 'الرمز',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم القضاء موجود مسبقاً في هذه المحافظة.',
            'governorate_id.exists' => 'المحافظة المختارة غير موجودة.',
        ];
    }
}
