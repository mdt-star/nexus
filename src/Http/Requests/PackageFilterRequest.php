<?php

namespace MdtStar\Nexus\Http\Requests;

class PackageFilterRequest extends FilterRequest
{
    protected function filterRules(): array
    {
        return [
            'name' => [
                'operator' => 'like',
                'rules' => 'nullable|string',
            ],
        ];
    }
}
