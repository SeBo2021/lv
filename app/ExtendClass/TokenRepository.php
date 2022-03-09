<?php

namespace App\ExtendClass;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class TokenRepository extends \Laravel\Passport\TokenRepository
{
    use PHPRedisTrait;

    /**
     * Get a token by the given ID.
     *
     * @param  string  $id
     * @return object
     */
    public function find($id): object
    {
        $tokenKey = $this->apiRedisKey['passport_token'].$id;
        $redis = $this->redis();
        if($redis->exists($tokenKey)){
            $token = $redis->hGetAll($tokenKey);
            Log::info('==PassportTokenProvider==',[$token]);
        }else{
            $token = parent::find($id)->toArray();
            if(!empty($token)){
                $redis->hMSet($tokenKey,$token);
            }
        }
        return (object)$token;

    }
}