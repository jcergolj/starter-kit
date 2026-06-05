<?php

declare(strict_types=1);

namespace App\Http\Requests;

class SaveLanguageRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'lang' => ['required', 'string', 'in:en,sl'],
        ];
    }
}
