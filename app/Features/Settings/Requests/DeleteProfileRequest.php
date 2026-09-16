<?php

declare(strict_types=1);

namespace App\Features\Settings\Requests;

use App\Http\Requests\AppFormRequest;

class DeleteProfileRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }
}
