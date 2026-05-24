<?php

namespace MdtStar\Nexus\Http\Requests;

class RoleFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
            'slug' => [
                'operator' => 'eq',
                'rules' => 'nullable|string',
            ],
        ];
    }
}
