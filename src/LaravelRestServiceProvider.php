<?php

namespace LaravelRest;

use LaravelRest\Http\Controllers\IndexController;
use LaravelRest\Http\Middleware\Token;
use LaravelRest\Http\Requests\StartRequest;
use Route;
use Illuminate\Support\ServiceProvider;

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


        $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if(preg_match('(\/api\/v1\/)', $url) === 1)
        {
            $this->app->alias('request', StartRequest::class);
        }

        Route::group(['prefix' => '/api/v1', 'middleware' => config('rest.middlewares')], function () {
            Route::any('call/{target}/{method}', ['as' => 'api.v1.call', 'uses' => IndexController::class . '@index']);
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

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
