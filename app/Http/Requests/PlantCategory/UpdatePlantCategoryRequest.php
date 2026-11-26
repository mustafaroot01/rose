<?php

namespace App\Http\Requests\PlantCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('plant_category')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('plant_categories', 'name')->ignore($id)->whereNull('deleted_at')],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الصنف', 'icon' => 'الأيقونة', 'color' => 'اللون'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'اسم الصنف موجود مسبقاً.'];
    }
}
