<?php

namespace App\Exceptions;

use RuntimeException;

class PermissionDeletionException extends RuntimeException
{
    public static function assignedToRoles(): self
    {
        return new self(__('This permission is granted to roles and cannot be deleted.'));
    }

    public static function assignedToUsers(): self
    {
        return new self(__('This permission is granted directly to users and cannot be deleted.'));
    }
}
