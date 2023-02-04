<?php

namespace Lidmo\UserPermission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{
    /**
     * Find a permission by its name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return Permission
     *
     * @throws \Lidmo\UserPermission\Exceptions\PermissionDoesNotExist
     */
    public static function findByName(string $name, $guardName): self;

    /**
     * Find a permission by its id.
     *
     * @param  int  $id
     * @param  string|null  $guardName
     * @return Permission
     *
     * @throws \Lidmo\UserPermission\Exceptions\PermissionDoesNotExist
     */
    public static function findById(int $id, $guardName): self;

    /**
     * Find or Create a permission by its name and guard name.
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return Permission
     */
    public static function findOrCreate(string $name, $guardName): self;
}
