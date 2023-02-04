<?php

namespace Lidmo\UserPermission\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lidmo\UserPermission\Contracts\Permission as PermissionContract;
use Lidmo\UserPermission\Exceptions\PermissionAlreadyExists;
use Lidmo\UserPermission\Exceptions\PermissionDoesNotExist;
use Lidmo\UserPermission\Guard;
use Lidmo\UserPermission\PermissionRegistrar;
use Lidmo\UserPermission\Traits\RefreshesPermissionCache;

class Permission extends Model implements PermissionContract
{
    use RefreshesPermissionCache;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
    }

    public function getTable()
    {
        return config('permission.table_names.permissions', parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $permission = static::getPermission(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']]);

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Lidmo\UserPermission\Contracts\Permission
     *
     * @throws \Lidmo\UserPermission\Exceptions\PermissionDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);
        if (! $permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     * @param  int  $id
     * @param  string|null  $guardName
     * @return \Lidmo\UserPermission\Contracts\Permission
     *
     * @throws \Lidmo\UserPermission\Exceptions\PermissionDoesNotExist
     */
    public static function findById(int $id, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermission([(new static())->getKeyName() => $id, 'guard_name' => $guardName]);

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param  string  $name
     * @param  string|null  $guardName
     * @return \Lidmo\UserPermission\Contracts\Permission
     */
    public static function findOrCreate(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermission(['name' => $name, 'guard_name' => $guardName]);

        if (! $permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     *
     * @param  array  $params
     * @param  bool  $onlyOne
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return app(PermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($params, $onlyOne);
    }

    /**
     * Get the current cached first permission.
     *
     * @param  array  $params
     * @return \Lidmo\UserPermission\Contracts\Permission
     */
    protected static function getPermission(array $params = []): ?PermissionContract
    {
        return static::getPermissions($params, true)->first();
    }
}
