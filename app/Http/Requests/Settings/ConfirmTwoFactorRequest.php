<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\AppFormRequest;

class ConfirmTwoFactorRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'min:6'],
        ];
    }
}
