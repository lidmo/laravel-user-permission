<?php

namespace Lidmo\UserPermission;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Lidmo\UserPermission\Contracts\Permission as PermissionContract;

class PermissionServiceProvider extends ServiceProvider
{
    public function boot(PermissionRegistrar $permissionLoader)
    {
        $this->offerPublishing();

        $this->registerMacroHelpers();

        $this->registerModelBindings();

        if ($this->app->config['permission.register_permission_check_method']) {
            $permissionLoader->clearClassPermissions();
            $permissionLoader->registerPermissions();
        }

        $this->app->singleton(PermissionRegistrar::class, function ($app) use ($permissionLoader) {
            return $permissionLoader;
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/permission.php',
            'permission'
        );
    }

    protected function offerPublishing()
    {
        if (! function_exists('config_path')) {
            // function not available and 'publish' not relevant in Lumen
            return;
        }

        $this->publishes([
            __DIR__.'/../config/permission.php' => config_path('permission.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_permission_tables.php.stub' => $this->getMigrationFileName('create_permission_tables.php'),
        ], 'migrations');
    }

    protected function registerModelBindings()
    {
        $config = $this->app->config['permission.models'];

        if (! $config) {
            return;
        }

        $this->app->bind(PermissionContract::class, $config['permission']);
    }
    protected function registerMacroHelpers()
    {
        if (! method_exists(Route::class, 'macro')) { // Lumen
            return;
        }

        Route::macro('permission', function ($permissions = []) {
            $permissions = implode('|', Arr::wrap($permissions));

            $this->middleware("permission:$permissions");

            return $this;
        });
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @return string
     */
    protected function getMigrationFileName($migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path.'*_'.$migrationFileName);
            })
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
