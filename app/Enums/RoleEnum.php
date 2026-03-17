<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case User = 'user';

    public function trans(): string
    {
        return match ($this) {
            self::Superadmin => __('Superadmin'),
            self::Admin => __('Admin'),
            self::User => __('User'),
        };
    }
}
