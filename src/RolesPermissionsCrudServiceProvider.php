<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud;

use Givanov95\RolesPermissionsCrud\Http\Controllers\PermissionController;
use Givanov95\RolesPermissionsCrud\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RolesPermissionsCrudServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/roles-permissions-crud.php',
            'roles-permissions-crud'
        );
    }

    public function boot(): void
    {
        $this->registerRouteMacro();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/roles-permissions-crud.php' => config_path('roles-permissions-crud.php'),
            ], 'roles-permissions-crud-config');
        }
    }

    /**
     * Register the Route::rolesPermissionsCrud() macro. Call it from your
     * application's route file to wire the roles & permissions resources under
     * the configured prefix / name / middleware in one line.
     */
    protected function registerRouteMacro(): void
    {
        Route::macro('rolesPermissionsCrud', function () {
            $config = config('roles-permissions-crud');

            Route::middleware($config['middleware'])
                ->prefix($config['prefix'])
                ->name($config['route_name_prefix'])
                ->group(function () {
                    Route::resource('roles', RoleController::class)->except(['show']);
                    Route::resource('permissions', PermissionController::class)->except(['show']);
                });
        });
    }
}
