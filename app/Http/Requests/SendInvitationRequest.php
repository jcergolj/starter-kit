<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Query\Builder;
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
                Rule::unique(Invitation::class)->where(function (Builder $query): void {
                    $query->whereNull('accepted_at')
                        ->where('expires_at', '>', now());
                }),
                Rule::unique(User::class, 'email'),
            ],
        ];
    }
}
