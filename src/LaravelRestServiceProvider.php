<?php

namespace LaravelRest;

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
        Route::group(['prefix' => '/api/v1', 'middleware' => ['api.token']], function () {
            Route::any('call/{target}/{method}', ['as' => 'api.v1.call', 'uses' => 'App\Api\V1\Controllers\IndexController@index']);
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
