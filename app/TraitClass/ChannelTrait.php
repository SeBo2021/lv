<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ChannelTrait
{
    public array $deviceSystems = [
        0 => '',
        1 => '苹果',
        2 => '安卓',
        3 => 'ios轻量版',
    ];

    public array $deviceSystemsSelect = [
        '' => [
            'id' =>'',
            'name' => '全部',
        ],
        1 => [
            'id' => 1,
            'name' => '苹果',
        ],
        2 => [
            'id' => 2,
            'name' => '安卓',
        ],
        3 => [
            'id' => 3,
            'name' => 'ios轻量版',
        ],
    ];

    public array $bindPhoneNumSelectData = [
        '' => [
            'id' =>'',
            'name' => '全部',
        ],
        0 => [
            'id' => 0,
            'name' => '未绑定',
        ],
        1 => [
            'id' => 1,
            'name' => '已绑定',
        ],
    ];

    public function getChannelSelectData($all=null): array
    {
        $queryBuild = DB::table('channels');
        if($all===null){
            $queryBuild = $queryBuild->where('status',1);
        }
        $items = [ ''=>'全部',0 => '官方'] + $queryBuild->pluck('name','id')->all();
        $lists = [];
        foreach ($items as $key => $value){
            $lists[$key] = [
                'id' => $key,
                'name' => $value,
            ];
        }
        return $lists;
    }

    //顶级渠道
    public function getTopChannels($type=null)
    {
        $buildChannel = DB::table('channels')->where('status',1);
        if($type!==null){
            $buildChannel = $buildChannel->where('type',$type);
        }
        $res = $buildChannel->where('pid',0)->get(['id','name']);
        $data = $this->uiService->allDataArr('请选择渠道(一级)');
        foreach ($res as $item) {
            $data[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
            ];
        }
        return $data;
    }

    public function getAllChannels($type=null): array
    {
        if($type!==null){
            $queryBuild = DB::table('channels')
                //->where('status',1)
                ->where('type',$type);
            $items = [ ''=>'全部'] + $queryBuild->pluck('name','id')->all();
            $lists = [];
            foreach ($items as $key => $value){
                $lists[$key] = [
                    'id' => $key,
                    'name' => $value,
                ];
            }
            return $lists;
        }
        return $this->getChannelSelectData();
    }

    public function writeChannelDeduction($id, $deduction=5000, $date=null)
    {
        $insertData = [
            'channel_id' => $id,
            'deduction' => $deduction,
            'created_at' =>$date ?? date('Y-m-d H:i:s'),
        ];
        DB::table('statistic_channel_deduction')->insert($insertData);
        DB::table('channel_day_statistics')->whereDate('date_at',date('Y-m-d'))->update(['deduction' => $deduction]);
    }

    public function createChannelAccount($model,$password='')
    {
        $insertChannelAccount = [
            'nickname' => $model->name,
            'account' => $model->number,
            'password' => $password,
            'created_at' => time(),
            'updated_at' => time(),
        ];
        $rid = DB::connection('channel_mysql')->table('admins')->insertGetId($insertChannelAccount);
        DB::connection('channel_mysql')->table('model_has_roles')->insert([
            'role_id' => 2,
            'model_id' => $rid,
            'model_type' => 'admin',
        ]);
    }

    //每日统计初始化数据
    public function initStatisticsByDay($channelId=0)
    {
        $currentDate = date('Y-m-d');
        $statistic_table = 'channel_day_statistics';
        $query = DB::table('channels');
        if($channelId >0){
            $query = $query->where('id',$channelId);
        }else{
            $query = $query->where('status',1);
        }
        $channels = $query->get();

        foreach ($channels as $channel) {
            $exists = DB::connection('master_mysql')->table($statistic_table)->where('channel_id', $channel->id)->where('date_at', $currentDate)->exists();
            if (!$exists) {
                $insertData = [
                    'channel_name' => $channel->name,
                    'channel_id' => $channel->id,
                    'channel_pid' => $channel->pid,
                    'channel_type' => $channel->type,
                    'channel_promotion_code' => $channel->promotion_code,
                    'channel_code' => $channel->number,
                    'channel_status' => 1,
                    'unit_price' => $channel->unit_price,
                    'share_ratio' => $channel->share_ratio,
                    'total_recharge_amount' => 0,
                    'total_amount' => 0,
                    'total_orders' => 0,
                    'order_index' => 0,
                    'usage_index' => 0,
                    'share_amount' => 0,
                    'date_at' => $currentDate,
                ];
                DB::table($statistic_table)->insert($insertData);
            }
        }
    }

    public function beforeSaveEventHandle($model, $id = '', $type=0)
    {
        $model->status = 1;
        $model->deduction *= 100;
        $model->type = $type;
        if($id>0){ //编辑
            /*if($model->deduction>0){
                $originalDeduction = $model->getOriginal()['deduction'];
                if($originalDeduction != $model->deduction){
                    //dd('修改扣量');
                    $this->writeChannelDeduction($id,$model->deduction);
                }
            }*/
            /*if($model->share_ratio>0){
                $originalShareRatio = $model->getOriginal()['share_ratio'];
                if($originalShareRatio != $model->share_ratio){
                    DB::table('channel_day_statistics')->whereDate('date_at',date('Y-m-d'))->update(['share_ratio' => $model->share_ratio]);
                }
            }*/
            $password = $this->rq->input('password');
            if($password){
                $exists = DB::connection('channel_mysql')->table('admins')->where('account',$model->number)->first();
                if($exists){
                    DB::connection('channel_mysql')->table('admins')->where('account',$model->number)->update(['password'=>bcrypt($password)]);
                }else{
                    $this->createChannelAccount($model,bcrypt($password));
                }
            }
        }
    }

    public function afterSaveSuccessEventHandle($model, $id = '')
    {
        $one = DB::table('domain')->where('status',1)->inRandomOrder()->first();
        $model->type += 0;
        $model->url = $one->name . '?' . http_build_query(['channel_id' => $model->promotion_code]);
        $model->fast_url = $one->name . '/downloadFast?' . http_build_query(['channel_id' => $model->promotion_code]);
        if($id == ''){ //添加
            $model->number = 'S'.Str::random(6) . $model->id;

            $model->statistic_url = env('RESOURCE_DOMAIN') . '/channel/index.html?' . http_build_query(['code' => $model->number]);
            //https://sao.yinlian66.com/channel/index.html?code=1

            $this->writeChannelDeduction($model->id,$model->deduction,$model->updated_at);
            //创建渠道用户
            $password = !empty($model->password) ? $model->password : bcrypt($model->number);
            $this->createChannelAccount($model,$password);
        }
        $updateData = [
            'deduction' => $model->deduction,
            'share_ratio' => $model->share_ratio,
            'unit_price' => $model->unit_price,
            'channel_code' => $model->number,
        ];
        DB::table('channel_day_statistics')->where('channel_id',$model->id)->whereDate('date_at',date('Y-m-d'))->update($updateData);
        $model->save();
        $this->initStatisticsByDay($model->id);
    }

    public function editTableHandle($request)
    {
        $this->rq = $request;
        $ids = $request->input('ids'); // 修改的表主键id批量分割字符串
        //分割ids
        $id_arr = explode(',', $ids);

        $id_arr = is_array($id_arr) ? $id_arr : [$id_arr];

        if (empty($id_arr)) {
            return $this->returnFailApi(lang('没有选择数据'));
        }
        //表格编辑过滤IDS
        $id_arr = $this->editTableFilterIds($id_arr);

        $field = $request->input('field'); // 修改哪个字段
        $value = $request->input('field_value'); // 修改字段值
        $id = 'id'; // 表主键id值

        $type_r = $this->editTableTypeEvent($id_arr, $field, $value);

        if ($type_r) {
            return $type_r;
        } else {
            if($field=='status'){
                DB::table('channel_day_statistics')->whereIn('channel_id', $id_arr)->where('date_at', date('Y-m-d'))->update(['channel_status'=>$value]);
            }
            $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }
    }

}