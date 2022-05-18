<?php

namespace App\ExtendClass;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class TokenRepository extends \Laravel\Passport\TokenRepository
{
    use PHPRedisTrait;

    /**
     * Get a token by the given ID.
     *
     * @param  string  $id
     */
    public function find($id)
    {
        $key = $this->apiRedisKey['passport_token'].$id;
        return Cache::remember($key, 7200, function() use($id) {
            return Passport::token()->where('id', $id)->first();
        });
    }
}