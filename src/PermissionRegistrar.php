<?php

namespace Lidmo\UserPermission;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Lidmo\UserPermission\Contracts\Permission;

class PermissionRegistrar
{
    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var \Illuminate\Cache\CacheManager */
    protected $cacheManager;

    /** @var string */
    protected $permissionClass;

    /** @var string */
    protected $userClass;

    /** @var \Illuminate\Database\Eloquent\Collection */
    protected $permissions;

    /** @var string */
    public static $pivotUser;

    /** @var string */
    public static $pivotPermission;

    /** @var \DateInterval|int */
    public static $cacheExpirationTime;

    /** @var string */
    public static $cacheKey;

    /** @var array */
    private $alias = [];

    /** @var array */
    private $except = [];

    /**
     * PermissionRegistrar constructor.
     *
     * @param  \Illuminate\Cache\CacheManager  $cacheManager
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->permissionClass = config('permission.models.permission');
        $this->userClass = config('permission.models.user');

        $this->cacheManager = $cacheManager;
        $this->initializeCache();
    }

    public function initializeCache()
    {
        self::$cacheExpirationTime = config('permission.cache.expiration_time') ?: \DateInterval::createFromDateString('24 hours');

        self::$cacheKey = config('permission.cache.key');

        self::$pivotUser = config('permission.column_names.user_pivot_key') ?: 'user_id';
        self::$pivotPermission = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): Repository
    {
        // the 'default' fallback here is from the permission.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('permission.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Register the permission check method on the gate.
     * We resolve the Gate fresh here, for benefit of long-running instances.
     *
     * @return bool
     */
    public function registerPermissions(): bool
    {
        app(Gate::class)->before(function (Authorizable $user, string $ability) {
            if (method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo($ability) ?: null;
            }
        });

        return true;
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions()
    {
        $this->permissions = null;

        return $this->cache->forget(self::$cacheKey);
    }

    /**
     * Clear class permissions.
     * This is only intended to be called by the PermissionServiceProvider on boot,
     * so that long-running instances like Swoole don't keep old data in memory.
     */
    public function clearClassPermissions()
    {
        $this->permissions = null;
    }

    /**
     * Load permissions from cache
     * This get cache and turns array into \Illuminate\Database\Eloquent\Collection
     */
    private function loadPermissions()
    {
        if ($this->permissions) {
            return;
        }

        $this->permissions = $this->cache->remember(self::$cacheKey, self::$cacheExpirationTime, function () {
            return $this->getSerializedPermissionsForCache();
        });

        // fallback for old cache method, must be removed on next mayor version
        if (! isset($this->permissions['alias'])) {
            $this->forgetCachedPermissions();
            $this->loadPermissions();

            return;
        }

        $this->alias = $this->permissions['alias'];

        $this->permissions = $this->getHydratedPermissionCollection();
    }

    /**
     * Get the permissions based on the passed params.
     *
     * @param  array  $params
     * @param  bool  $onlyOne
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $this->loadPermissions();

        $method = $onlyOne ? 'first' : 'filter';

        $permissions = $this->permissions->$method(static function ($permission) use ($params) {
            foreach ($params as $attr => $value) {
                if ($permission->getAttribute($attr) != $value) {
                    return false;
                }
            }

            return true;
        });

        if ($onlyOne) {
            $permissions = new Collection($permissions ? [$permissions] : []);
        }

        return $permissions;
    }

    /**
     * Get an instance of the permission class.
     *
     * @return \Lidmo\UserPermission\Contracts\Permission
     */
    public function getPermissionClass(): Permission
    {
        return app($this->permissionClass);
    }

    public function setPermissionClass($permissionClass)
    {
        $this->permissionClass = $permissionClass;
        config()->set('permission.models.permission', $permissionClass);
        app()->bind(Permission::class, $permissionClass);

        return $this;
    }

    /**
     * Get an instance of the user class.
     *
     * @return Authorizable
     */
    public function getUserClass(): Authorizable
    {
        return app($this->userClass);
    }

    public function setUserClass($userClass)
    {
        $this->userClass = $userClass;

        return $this;
    }

    public function getCacheRepository(): Repository
    {
        return $this->cache;
    }

    public function getCacheStore(): Store
    {
        return $this->cache->getStore();
    }

    /**
     * Changes array keys with alias
     *
     * @return array
     */
    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())->except($this->except)
            ->keyBy(function ($value, $key) {
                return $this->alias[$key] ?? $key;
            })->all();
    }

    /**
     * Array for cache alias
     */
    private function aliasModelFields($newKeys = []): void
    {
        $i = 0;
        $alphas = ! count($this->alias) ? range('a', 'h') : range('j', 'p');

        foreach (array_keys($newKeys->getAttributes()) as $value) {
            if (! isset($this->alias[$value])) {
                $this->alias[$value] = $alphas[$i++] ?? $value;
            }
        }

        $this->alias = array_diff_key($this->alias, array_flip($this->except));
    }

    /*
     * Make the cache smaller using an array with only required fields
     */
    private function getSerializedPermissionsForCache()
    {
        $this->except = config('permission.cache.column_names_except', ['created_at', 'updated_at', 'deleted_at']);

        $permissions = $this->getPermissionClass()->all()
            ->map(function ($permission) {
                if (! $this->alias) {
                    $this->aliasModelFields($permission);
                }

                return $this->aliasedArray($permission);
            })->all();

        return ['alias' => array_flip($this->alias)] + compact('permissions');
    }

    private function getHydratedPermissionCollection()
    {
        $permissionClass = $this->getPermissionClass();
        $permissionInstance = new $permissionClass();

        return Collection::make(
            array_map(function ($item) use ($permissionInstance) {
                return $permissionInstance
                    ->newFromBuilder($this->aliasedArray(array_diff_key($item, ['r' => 0])));
            }, $this->permissions['permissions'])
        );
    }
}
