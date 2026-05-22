<?php

namespace MdtStar\Nexus\Http\Requests;

/**
 * 模型访问权限查询请求
 *
 * 公开接口，允许按主体类型、主体ID、模型类名过滤。
 */
class ModelAccessFilterRequest extends FilterRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function filterRules(): array
    {
        return [
            'subject_type' => [
                'operator' => 'eq',
                'rules'    => 'nullable|string',
            ],
            'subject_id' => [
                'operator' => 'eq',
                'rules'    => 'nullable|integer',
            ],
            'class' => [
                'operator' => 'like',
                'rules'    => 'nullable|string',
            ],
        ];
    }
}
