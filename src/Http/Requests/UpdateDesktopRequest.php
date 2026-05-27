<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesktopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'region' => ['sometimes', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
