<?php

namespace MdtStar\Nexus\Http\Requests;

class PermissionableFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'model_type' => [
                'operator' => 'eq',
                'rules' => 'nullable|string',
            ],
            'model_id' => [
                'operator' => 'eq',
                'rules' => 'nullable|integer',
            ],
            'tag' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
            'package_id' => [
                'operator' => 'eq',
                'rules' => 'nullable|integer',
            ],
        ];
    }
}
