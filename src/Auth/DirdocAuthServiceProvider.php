<?php namespace UTEM\Dirdoc\Auth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Guard;

class DirdocAuthServiceProvider extends ServiceProvider {

    public function boot()
    {
        $this->app['auth']->extend('dirdoc', function($app)
        {
            $model = $this->app['config']->get('auth.model');

            $provider = new DirdocUserProvider($model);

            return new Guard($provider, $this->app['session.store']);
        }); 
    }

    public function register()
    {
    }
}
