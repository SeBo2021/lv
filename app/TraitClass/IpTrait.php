<?php

namespace App\TraitClass;

trait IpTrait
{
    public static function getRealIp()
    {
        //return $_SERVER['HTTP_X_REAL_IP'] ?? \request()->getClientIp();
        return $_SERVER['HTTP_X_REAL_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? \request()->getClientIp());
    }
}