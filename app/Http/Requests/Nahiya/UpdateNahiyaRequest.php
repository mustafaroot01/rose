<?php

namespace App\Http\Requests\Nahiya;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNahiyaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('nahiya')?->id;

        return [
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('nahiyas', 'name')
                    ->ignore($id)
                    ->where('district_id', $this->input('district_id'))
                    ->whereNull('deleted_at'),
            ],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'district_id' => 'القضاء',
            'name' => 'اسم الناحية',
            'code' => 'الرمز',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'اسم الناحية موجود مسبقاً في هذا القضاء.',
            'district_id.exists' => 'القضاء المختار غير موجود.',
        ];
    }
}
