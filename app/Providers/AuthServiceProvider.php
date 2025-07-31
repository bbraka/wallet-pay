<?php

namespace App\Providers;

use App\Auth\BearerTokenGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Register custom bearer token guard
        Auth::extend('bearer-token', function ($app, $name, array $config) {
            $guard = new BearerTokenGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $config['input_key'] ?? 'api_token',
                $config['storage_key'] ?? 'api_token',
                $config['hash'] ?? false
            );

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}