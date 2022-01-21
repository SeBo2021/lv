<?php


namespace App\Http\Controllers\Admin;


use App\Models\Recharge;
use App\Services\UiService;
use Illuminate\Support\Facades\DB;

class RechargeController extends BaseCurlIndexController
{
    public function setModel()
    {
        return $this->model = new Recharge();
    }

    public function indexCols()
    {
        $cols = [
            [
                'type' => 'checkbox'
            ],
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'uid',
                'width' => 100,
                'title' => '会员',
                'align' => 'center'
            ],
            [
                'field' => 'amount',
                'minWidth' => 100,
                'title' => '金额',
                'align' => 'center'
            ],
            [
                'field' => 'type',
                'minWidth' => 100,
                'title' => '充值方式',
                'align' => 'center'
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
        return $cols;
    }

    /*public function setListOutputItemExtend($item)
    {
        return $item;
    }*/

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

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            [
                'field' => 'query_channel_id',
                'type' => 'select',
                'name' => '选择渠道',
                'data' => $this->getChannelSelectData()
            ],
            [
                'field' => 'query_order_type',
                'type' => 'select',
                'name' => '订单类型',
                'data' => [
                    1=>'会员卡',
                    2=>'视频',
                    3=>'骚豆',
                ]
            ],
            [
                'field' => 'query_device_system',
                'type' => 'select',
                'name' => '手机系统平台',
                'data' => [
                    1=>'苹果',
                    2=>'安卓',
                ]
            ],

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

}