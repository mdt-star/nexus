<?php

namespace MdtStar\Nexus\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderDesktopItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:desktop_items,id'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }
}
