<?php

namespace MdtStar\Nexus\Http\Requests;

class DesktopFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'user_id' => [
                'operator' => 'eq',
                'rules' => 'nullable|integer',
            ],
            'region' => [
                'operator' => 'eq',
                'rules' => 'nullable|string',
            ],
            'is_default' => [
                'operator' => 'eq',
                'rules' => 'nullable|boolean',
            ],
        ];
    }
}
