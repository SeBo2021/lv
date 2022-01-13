<?php

namespace App\TraitClass;

use App\Models\WhiteList;
use App\TraitClass\IpTrait;

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
        if(!in_array($ip, $whiteList)){
            return false;
        }
        return true;
    }
}