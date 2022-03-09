<?php

namespace App\Providers;

use App\ExtendClass\ClientRepository;

class PassportServiceProvider extends \Laravel\Passport\PassportServiceProvider
{
    /**
     * Register the client repository.
     *
     * @return void
     */
    protected function registerClientRepository(): void
    {
        $this->app->singleton(ClientRepository::class, function ($container) {
            $config = $container->make('config')->get('passport.personal_access_client');

            return new ClientRepository($config['id'] ?? null, $config['secret'] ?? null);
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerTokenRepository();

        parent::register();
    }

    public function registerTokenRepository()
    {

    }

}