<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\AppFormRequest;
use App\Models\User;
use App\ValueObjects\EmailAddress;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends AppFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => EmailAddress::normalize($this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
