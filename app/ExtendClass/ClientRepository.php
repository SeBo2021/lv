<?php

namespace App\ExtendClass;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class ClientRepository extends \Laravel\Passport\ClientRepository
{
    use PHPRedisTrait;
    /**
     * Get a client by the given ID.
     *
     * @param  int  $id
     * @return \Laravel\Passport\Client|null
     */
    public function find($id)
    {
        Log::info('==ClientRepository==',[$id]);
        $key = $this->apiRedisKey['passport_client'].$id;
        $redis = $this->redis();
        if($redis->exists($key)){
            $res = unserialize($redis->get($key));
        }else{
            $client = Passport::client();
            $res = $client->where($client->getKeyName(), $id)->first();
            $redis->set($key,serialize($res));
        }
        return $res;
        /*return Cache::remember("passport:client:{$id}", 86400,
            function () use ($id) {
                $client = Passport::client();
                return $client->where($client->getKeyName(), $id)->first();
            }
        );*/
    }
}