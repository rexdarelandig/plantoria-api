<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'scientific_name' => ['sometimes', 'required', 'string', 'max:255'],
            'location_id' => ['sometimes', 'required', 'exists:locations,id'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048', 'url'],
        ];
    }
}
