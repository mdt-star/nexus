<?php

namespace MdtStar\Nexus\Http\Requests;

class ModelScopeFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'key' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
            'class' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
        ];
    }
}
