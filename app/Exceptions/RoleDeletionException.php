<?php

namespace App\Exceptions;

use RuntimeException;

class RoleDeletionException extends RuntimeException
{
    public static function protectedRole(): self
    {
        return new self(__('The admin role cannot be deleted.'));
    }

    public static function roleHasUsers(): self
    {
        return new self(__('This role has users assigned to it and cannot be deleted.'));
    }
}
