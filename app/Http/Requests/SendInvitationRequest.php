<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Invitation::class)->whereNull('accepted_at'),
                Rule::unique(User::class, 'email'),
            ],
        ];
    }
}
