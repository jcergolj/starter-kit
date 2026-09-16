<?php

declare(strict_types=1);

namespace App\Features\UserManagement\Requests;

use App\Http\Requests\AppFormRequest;
use App\Models\User;
use App\ValueObjects\EmailAddress;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends AppFormRequest
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
            'username' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class)->ignore($this->route('user')),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->route('user')),
            ],
        ];
    }
}
