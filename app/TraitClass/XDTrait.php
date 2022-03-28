<?php

namespace App\TraitClass;

trait XDTrait
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
        $sig_data = md5($data['fxid'] . $data['fxddh'] . $data['fxfee'] . $data['fxnotifyurl'] . $md5Key);
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
        $sig_data = md5($data['fxstatus'] . $data['fxid'] . $data['fxddh'] . $data['fxfee'] . $md5Key);
        if ($sig_data == $pubKey) {
            return true;
        }
        return false;
    }
}