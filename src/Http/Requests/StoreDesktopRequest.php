<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesktopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
