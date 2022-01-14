<?php

namespace App\TraitClass;

use App\Models\WhiteList;
use App\TraitClass\IpTrait;
use Illuminate\Support\Facades\Log;

trait WhiteListTrait
{
    public function whitelistPolice()
    {
        $ip = IpTrait::getRealIp();
        //白名单
        $whiteList = WhiteList::query()
            ->where('status',1)
            ->where('type',1)
            ->pluck('ip')->toArray();
        Log::info('===adminLoginIPS===',[$whiteList,$ip]);
        if(!in_array($ip, $whiteList)){
            return false;
        }
        return true;
    }
}