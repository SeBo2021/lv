<?php


namespace App\Http\Controllers\Admin;


use App\Models\Recharge;
use App\Services\UiService;
use App\TraitClass\ChannelTrait;
use App\TraitClass\MemberCardTrait;
use Illuminate\Support\Facades\DB;

class RechargeController extends BaseCurlIndexController
{
    use ChannelTrait,MemberCardTrait;

    public array $orderType = [
        0=>[
            'id'=>'',
            'name'=>'全部'
        ],
        1=>[
            'id'=>1,
            'name'=>'会员卡'
        ],
        2=>[
            'id'=>2,
            'name'=>'骚豆'
        ],
    ];

    public array $deviceSystem = [
        0=>[
            'id'=>'',
            'name'=>'全部'
        ],
        1=>[
            'id'=>1,
            'name'=>'苹果'
        ],
        2=>[
            'id'=>2,
            'name'=>'安卓'
        ]
    ];

    public function setModel()
    {
        return $this->model = new Recharge();
    }

    public function indexCols(): array
    {
        return [
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
                'field' => 'channel_id',
                'width' => 100,
                'title' => '推广渠道',
                'align' => 'center'
            ],
            [
                'field' => 'uid',
                'width' => 100,
                'title' => '会员ID',
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
                'title' => '订单类型',
                'align' => 'center'
            ],
            [
                'field' => 'device_system',
                'minWidth' => 100,
                'title' => '手机系统平台',
                'align' => 'center'
            ],
            [
                'field' => 'status',
                'minWidth' => 100,
                'title' => '状态',
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
    }

    public function setListOutputItemExtend($item)
    {
        $item->channel_id = isset($this->getChannelSelectData(true)[$item->channel_id]) ? $this->getChannelSelectData(true)[$item->channel_id]['name'] : '该渠道被删除';
        if($item->type!=1){
            $item->type = '金币';
        }else{
            $remark = json_decode($item->remark,true);
            $item->type = $remark['name'] ?? '';
        }
        $item->status = UiService::switchTpl('status', $item,'','完成|未付');
        $item->device_system = $this->deviceSystem[$item->device_system]['name'];
        return $item;
    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            [
                'field' => 'channel_id',
                'type' => 'select',
                'name' => '选择渠道',
                'data' => $this->getChannelSelectData()
            ],
            [
                'field' => 'type',
                'type' => 'select',
                'name' => '订单类型',
                'data' => $this->getMemberCardList(),
            ],
            [
                'field' => 'query_device_system',
                'type' => 'select',
                'name' => '手机系统平台',
                'data' => $this->deviceSystem
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function handleResultModel($model)
    {
        $type = $this->rq->input('type',null);
        $channel_id = $this->rq->input('channel_id',null);
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);
        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);
//        $build = DB::table('recharge')
        $build = $model->join('orders','recharge.order_id','=','orders.id');
        //dump($channel_id);
        if($channel_id!==null){
            $build = $build->where('recharge.channel_id',$channel_id);
        }
        if($type!==null){
            if($type == 0){
                $build = $build->where('orders.type',2);
            }else{
                $build = $build->where('orders.type',1)->where('orders.type_id',$type);
            }
        }

        $total = $build->count();
        $field = ['recharge.id','recharge.amount','recharge.uid','recharge.order_id','orders.status',
            'recharge.channel_id','recharge.device_system','recharge.created_at','orders.type','orders.type_id','orders.remark'];
        $currentPageData = $build->forPage($page, $pagesize)->get($field);
        return [
            'total' => $total,
            'result' => $currentPageData
        ];
    }

}