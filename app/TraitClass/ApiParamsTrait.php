<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use JetBrains\PhpStorm\ArrayShape;

trait ApiParamsTrait
{
    /**
     * @param $params
     * @param false $combine 是否合并成字符串
     * @return array|mixed|string
     */
    public static function parse($params, bool $combine = false): mixed
    {
        if(\Illuminate\Support\Env::get('MIDDLEWARE_SECRET')) {
            $p = Crypt::decryptString($params);
            $p = json_decode($p,true);
            if ($combine) {
                return implode(',',($p['params'] ?? $p));
            }
            return $p['params'] ?? $p;
        }else{
            if(is_array($params)){
                if ($combine) {
                    return implode(',',$params);
                }
                return $params;
            }else{
                return json_decode($params,true);
            }
        }
    }

    /**
     * 统一输出格式
     * @param int $status
     * @param array $data
     * @param string $message
     * @return array
     */
    #[ArrayShape(['state' => "int", 'data' => "array", 'msg' => "string"])] public function format(int $status = 0, array $data = [], string $message = ''): array
    {
        return [
            'state' => $status,
            'data' => $data,
            'msg' => $message,
        ];
    }
}