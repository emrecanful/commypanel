<?php

namespace App\Http\Requests;

class StoreAppRequest extends AppSettingsRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100'], ...parent::rules()];
    }
}
