<?php

namespace App\TraitClass;

use App\ExtendClass\Rsa;

trait CJTrait
{
    /**
     * 签名算法
     * @param $data
     * @param $md5Key
     * @param $privateKey
     * @return string
     */
    function sign($data, $md5Key, $privateKey): string
    {
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $arg = $arg . 'key=' . $md5Key;

        //签名数据转换为大写
        $sig_data = strtoupper(md5($arg));
        //使用RSA签名
        $rsa = new Rsa('', $privateKey);
        //私钥签名
        return $rsa->sign($sig_data);
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
        //验签
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $arg = $arg . 'key=' . $md5Key;
        $signData = strtoupper(md5($arg));
        $rsa = new Rsa($pubKey, '');
        if ($rsa->verify($signData, $data['sign']) == 1) {
            return true;
        }
        return false;
    }
}