<?php

namespace MdtStar\Nexus\Http\Requests;

class UserFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
            'email' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
        ];
    }
}
