<?php

namespace MdtStar\Nexus\Http\Requests;

/**
 * 动态配置查询请求
 *
 * 支持按 key、type 过滤，默认只查非 null 类型的配置。
 */
class SystemConfigFilterRequest extends FilterRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function filterRules(): array
    {
        return [
            'key' => [
                'operator' => 'like',
                'rules'    => 'nullable|string',
            ],
            'type' => [
                'operator' => 'eq',
                'rules'    => 'nullable|string|in:boolean,number,json,null,string',
            ],
        ];
    }
}
