<?php

namespace App\Exceptions;

use RuntimeException;

class LastAdminException extends RuntimeException
{
    public static function cannotRemoveRole(): self
    {
        return new self(__('At least one user must keep the admin role.'));
    }

    public static function cannotDeleteUser(): self
    {
        return new self(__('You cannot delete the last administrator.'));
    }
}
