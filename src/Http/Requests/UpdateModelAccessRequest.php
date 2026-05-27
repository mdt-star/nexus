<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 更新模型访问权限请求验证
 */
class UpdateModelAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'can_read'   => 'boolean',
            'can_write'  => 'boolean',
            'can_delete' => 'boolean',
            'scope_key'  => 'nullable|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
