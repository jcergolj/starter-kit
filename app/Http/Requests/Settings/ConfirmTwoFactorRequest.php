<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\AppFormRequest;

class ConfirmTwoFactorRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }
}
