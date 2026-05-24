<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag' => ['sometimes', 'string', 'max:255'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'parent_id' => ['nullable', 'integer', 'exists:permissions,id'],
        ];
    }
}
