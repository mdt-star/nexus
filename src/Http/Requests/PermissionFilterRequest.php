<?php

namespace MdtStar\Nexus\Http\Requests;

class PermissionFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'tag' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
            'package_id' => [
                'operator' => 'eq',
                'rules' => 'nullable|integer',
            ],
            'parent_id' => [
                'operator' => 'eq',
                'rules' => 'nullable|integer',
            ],
        ];
    }
}
