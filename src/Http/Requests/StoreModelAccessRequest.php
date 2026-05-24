<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建模型访问权限请求验证
 *
 * subject_type 接受任意模型全限定类名字符串，
 * 不限制为特定类型，支持多态主体设计（User/Role/Team/Organization 等）。
 */
class StoreModelAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject_type' => 'required|string',
            'subject_id'   => 'required|integer',
            'class'        => 'required|string',
            'can_read'     => 'boolean',
            'can_write'    => 'boolean',
            'can_delete'   => 'boolean',
            'scope_key'    => 'nullable|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
