<?php

namespace Lidmo\UserPermission\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedException extends HttpException
{
    private $requiredUsers = [];

    private $requiredPermissions = [];

    public static function forUsers(array $users): self
    {
        $message = 'User does not have the right users.';

        if (config('permission.display_user_in_exception')) {
            $message .= ' Necessary users are '.implode(', ', $users);
        }

        $exception = new static(403, $message, null, []);
        $exception->requiredUsers = $users;

        return $exception;
    }

    public static function forPermissions(array $permissions): self
    {
        $message = 'User does not have the right permissions.';

        if (config('permission.display_permission_in_exception')) {
            $message .= ' Necessary permissions are '.implode(', ', $permissions);
        }

        $exception = new static(403, $message, null, []);
        $exception->requiredPermissions = $permissions;

        return $exception;
    }

    public static function forUsersOrPermissions(array $usersOrPermissions): self
    {
        $message = 'User does not have any of the necessary access rights.';

        if (config('permission.display_permission_in_exception') && config('permission.display_user_in_exception')) {
            $message .= ' Necessary users or permissions are '.implode(', ', $usersOrPermissions);
        }

        $exception = new static(403, $message, null, []);
        $exception->requiredPermissions = $usersOrPermissions;

        return $exception;
    }

    public static function notLoggedIn(): self
    {
        return new static(403, 'User is not logged in.', null, []);
    }

    public function getRequiredUsers(): array
    {
        return $this->requiredUsers;
    }

    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }
}
