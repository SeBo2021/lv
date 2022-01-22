<?php

namespace App\TraitClass;

trait IpTrait
{
    public static function getRealIp()
    {
        //return $_SERVER['HTTP_X_REAL_IP'] ?? \request()->getClientIp();
        return $_SERVER['HTTP_X_REAL_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? \request()->getClientIp());
    }

    public function forceToIpV4($ip): string
    {
        $IPV4 = $ip;
        if(filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $IPV4 = hexdec(substr($ip, 0, 2)). "." . hexdec(substr($ip, 2, 2)). "." . hexdec(substr($ip, 5, 2)). "." . hexdec(substr($ip, 7, 2));
        }
        return $IPV4;
    }
}