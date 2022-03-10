<?php

namespace App\Providers;

use App\ExtendClass\ClientRepository;
use App\ExtendClass\TokenRepository;

class PassportServiceProvider extends \Laravel\Passport\PassportServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTokenRepository();

        parent::register();
    }

    /**
     * Register the client repository.
     *
     * @return void
     */
    protected function registerClientRepository()
    {
        $this->app->singleton('Laravel\Passport\ClientRepository', function ($container) {
            $config = $container->make('config')->get('passport.personal_access_client');
            return new ClientRepository($config['id'] ?? null, $config['secret'] ?? null);
        });
    }

    /**
     * Register the client repository.
     *
     * @return void
     */
    protected function registerTokenRepository()
    {
        $this->app->singleton('Laravel\Passport\TokenRepository', function ($container) {
            return new TokenRepository();
        });
    }

}