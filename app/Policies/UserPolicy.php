<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $target->isAdmin();
    }
}
