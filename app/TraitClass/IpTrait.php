<?php

namespace App\TraitClass;

trait IpTrait
{
    public function getRealIp(): string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? \request()->getClientIp();
        return $this->forceToIpV4($ip);
    }

    public function forceToIpV4($ip): string
    {
        $IPV4 = $ip;
        if(filter_var($ip,FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
            $IPV4 = @hexdec(substr($ip, 0, 2)). "." . @hexdec(substr($ip, 2, 2)). "." . @hexdec(substr($ip, 5, 2)). "." . @hexdec(substr($ip, 7, 2));
        }
        return $IPV4;
    }
}