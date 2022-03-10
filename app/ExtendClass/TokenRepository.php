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
    public function find($id)
    {
        Log::info('==TokenRepository==',[$id]);
        $key = $this->apiRedisKey['passport_token'].$id;
        $redis = $this->redis();
        if($redis->exists($key)){
            $res = unserialize($redis->get($key));
        }else{
            $res = Passport::token()->where('id', $id)->first();
            $redis->set($key,serialize($res));
        }
        return $res;
        /*return Cache::remember("passport:token:{$id}", 86400,
            function () use ($id) {
                return Passport::token()->where('id', $id)->first();
            }
        );*/
    }
}