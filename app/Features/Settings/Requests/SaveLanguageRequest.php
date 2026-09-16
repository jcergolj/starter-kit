<?php

declare(strict_types=1);

namespace App\Features\Settings\Requests;

use App\Http\Requests\AppFormRequest;

class SaveLanguageRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'lang' => ['required', 'string', 'in:en,sl'],
        ];
    }
}
