<?php

namespace MdtStar\Nexus\Http\Requests;

/**
 * 管理端模型访问权限查询请求
 *
 * 需要管理员权限，支持更多过滤字段。
 */
class AdminModelAccessFilterRequest extends FilterRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
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
            'id' => [
                'operator' => 'in',
                'rules'    => 'nullable|string',
            ],
            'can_read' => [
                'operator' => 'eq',
                'rules'    => 'nullable|boolean',
            ],
            'can_write' => [
                'operator' => 'eq',
                'rules'    => 'nullable|boolean',
            ],
            'can_delete' => [
                'operator' => 'eq',
                'rules'    => 'nullable|boolean',
            ],
            'created_at' => [
                'operator' => 'between',
                'rules'    => 'nullable|string',
            ],
        ];
    }
}
