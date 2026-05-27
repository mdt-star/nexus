<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'regex:/^[A-Za-z0-9_\\\\]+@\d+$/'],
            'tag' => ['required', 'string', 'max:255'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }
}
