<?php

namespace App\ExtendClass;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class TokenRepository extends \Laravel\Passport\TokenRepository
{
    use PHPRedisTrait;

    /**
     * Get a token by the given ID.
     *
     * @param  string  $id
     * @return \Laravel\Passport\Token
     */
    public function find($id): \Laravel\Passport\Token
    {
        $key = $this->apiRedisKey['passport_token'].$id;
        $redis = $this->redis();
        if($redis->exists($key)){
            $res = unserialize($redis->get($key));
        }else{
            $res = Passport::token()->where('id', $id)->first();
            $redis->set($key,serialize($res));
            $redis->expire($key,7200);
        }
        return $res;
    }
}