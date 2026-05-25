<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesktopItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'path' => ['sometimes', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'component' => ['nullable', 'string', 'max:500'],
            'custom' => ['nullable', 'json'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'integer', 'exists:desktop_items,id'],
        ];
    }
}
