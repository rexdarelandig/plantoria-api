<?php

namespace App\Http\Requests;

use App\Models\Plant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plant'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Plant $plant */
        $plant = $this->route('plant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'scientific_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048', 'url'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('plants', 'slug')->ignore($plant->id),
            ],
        ];
    }
}
