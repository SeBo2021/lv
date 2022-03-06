<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    use PHPRedisTrait,AdTrait;

    public function ack(): \Illuminate\Http\JsonResponse
    {
        $configKey = 'api_config';
        $configData = $this->redis()->get($configKey);
        if($configData){
            return response()->json([
                'state'=>0,
                'data'=>json_decode($configData,true)
            ]);
        }else{
            $res = $this->getConfigDataFromDb();
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
    }

    /*public function upgrade(Request $request)
    {
        if(!isset($request->params)){
            return response()->json(['state'=>-1, 'msg'=>'参数错误']);
        }
        $params = Crypt::decryptString($request->params);
        $params = json_decode($params,true);
        $appid = $params['appid'];
        $version = $params['version'];
        $config = config_cache_default('config');
        if(!empty($config)){
            if($appid!=$config['app_id']){
                return response()->json(['state'=>-1, 'msg'=>'应用标识错误']);
            }
            $status = $config['app_version_name']!=$version ? 1 : 0;
            return response()->json([
                'state'=>0,
                'data'=>[
                    'status'=>$status,
                    'note'=>$config['app_update_content'],
                    'url'=>$config['app_update_url']
                ],
                'msg'=>'更新提示'
            ]);
        }
        return 0;
    }*/

}
