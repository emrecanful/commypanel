<?php

namespace App\Http\Requests;

class UpdateAppRequest extends AppSettingsRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100'], ...parent::rules()];
    }
}
