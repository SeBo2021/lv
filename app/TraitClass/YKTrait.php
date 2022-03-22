<?php

namespace App\TraitClass;

trait YKTrait
{
    /**
     * 签名算法
     * @param $data
     * @param $md5Key
     * @return string
     */
    function sign($data, $md5Key): string
    {
        //签名数据转换为大写
        $sig_data = strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount'] . $data['notifyCallback']));
        return $sig_data;
    }

    /**
     * 验签
     * @param $data
     * @param $md5Key
     * @param $pubKey
     * @return bool
     */
    function verify($data, $md5Key, $pubKey): bool
    {
        $sig_data = strtolower(md5($md5Key . $data['orderNo'] . $data['appId'] . $data['amount']));
        if ($sig_data == $pubKey) {
            return true;
        }
        return false;
    }
}