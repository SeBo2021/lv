<?php
namespace App\Http\Controllers\Admin;

use App\Models\Config;
use App\Models\User;
use App\Models\Withdraw;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;

class RedisOperateController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = 'redis操作';


    public function index()
    {
        $res = request('res')??'';
        $type = request('type')??'';
        $method = request('method')??'';
        $parameters = request('parameters')??'';
        return $this->display(['res'=>$res,'type'=>$type,'method'=>$method,'parameters'=>$parameters]);
    }

    public function submitPost(Request $request)
    {
        /* return response()->json([
            'code' => 200,
            'msg' => '提交成功'
        ]); */
        $params = $request->all(['method','parameters','type']);
        
        
        Redis::connection()->command('select', [$params['type']]);
        $disabledMethods = ['flushdb','flushall','config'];
        $method = $params['method']; 
        if(in_array(strtolower($method),$disabledMethods)){
            return redirect()->route('admin.redisOperate.index',['res'=>'禁用此命令']);
        }
        $parameters = explode(' ',$params['parameters']);
        $res = Redis::connection()->command($method,$parameters);
        //dump($res);exit;
        $res = json_encode($res);
        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route('admin.redisOperate.index',['res'=>$res,'type'=>$params['type'],'method'=>$params['method'],'parameters'=>$params['parameters']]);
    }

}
