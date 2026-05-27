<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建/更新动态配置请求验证
 */
class StoreSystemConfigRequest extends FormRequest
{
    public function rules(): array
    {
        $configId = $this->route('config')?->id;

        return [
            'key'         => $configId
                ? 'nullable|string'
                : 'required|string|unique:configs,key',
            'value'       => 'required',
            'description' => 'nullable|string',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
