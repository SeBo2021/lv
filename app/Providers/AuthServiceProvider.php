<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     * 注册任何认证/授权服务。
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        //将redis注入Auth中
        Auth::provider('redis',function($app, $config){
            return new RedisUserProvider($app['hash'], $config['model']);
        });
        if (! $this->app->routesAreCached()) {
            Passport::routes();
        }
        Passport::tokensCan([
            'channel-download' => 'user download app package',
            'check-user' => 'app user login'
        ]);
    }
}
