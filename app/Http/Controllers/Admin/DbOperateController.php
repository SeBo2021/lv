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
use Illuminate\Support\Facades\Response;

class DbOperateController extends BaseCurlController
{
    use PHPRedisTrait,AdTrait;
    //去掉公共模板
    public $commonBladePath = '';
    public $pageName = 'DB操作';


    public function index()
    {
        $parameters = request('res')??'';
        $querySql = request('querySql')??'';
        $type = request('type')??'';
        return $this->display(['parameters'=>$parameters,'querySql'=>$querySql,'type'=>$type]);
    }

    public function submitPost(Request $request)
    {
        /* return response()->json([
            'code' => 200,
            'msg' => '提交成功'
        ]); */
        $params = $request->all(['querySql','type']);
        $sql = $params['querySql']??'';
        
        $res = match($params['type']){
            'select' =>  DB::connection()->select($sql),
            'update' =>  DB::connection()->update($sql)
        };
        // $res = DB::connection()->delete($sql);
        $res = is_int($res) ?: json_encode($res);
        return $request->wantsJson()
            ? new Response('', 204)
            : redirect()->route('admin.dbOperate.index',['res'=>$res,'querySql'=>$params['querySql'],'type'=>$params['type']]);
    }

}
