<?php

namespace Juzaweb\Backend\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function rules()
    {
        return [
            'file' => 'required|string',
            'type' => 'required|in:blogger'
        ];
    }
}
