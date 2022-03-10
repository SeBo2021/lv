<?php

namespace App\Providers;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisUserProvider extends EloquentUserProvider
{
    use PHPRedisTrait;

    public function __construct($hasher, $model)
    {
        parent::__construct($hasher, $model);
    }
    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {

        if (!isset($credentials['token'])) {
            return;
        }

        $token = $credentials['token'];
        $redis = $this->redis();
        $userId = $redis->get($token);
        Log::info('RedisUserProvider',[$userId]);
        return $this->retrieveById($userId);
    }

}