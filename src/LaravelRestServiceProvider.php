<?php

namespace LaravelRest;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use LaravelRest\Http\Controllers\RoleRouteController;
use LaravelRest\Http\Requests\DefaultRequest;
use Route;

class LaravelRestServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/publish' => public_path() . '/../app',
            __DIR__ . '/config' => public_path() . '/../config',
        ], 'public');

        Route::group(['prefix' => '/api/v1', 'middleware' => config('rest.middlewares')], function () {
            Route::any('call/{target}/{method}', ['as' => 'api.v1.call', 'uses' => RoleRouteController::class . '@index']);
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(DefaultRequest::class, function ($app) {
            /** @var Request $baseRequest */
            $baseRequest = $app->make(Request::class);
            // Создаём наш расширенный Request с данными из базового
            return DefaultRequest::createFrom($baseRequest);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {

    }
}
