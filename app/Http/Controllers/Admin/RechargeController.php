<?php


namespace App\Http\Controllers\Admin;


use App\Models\Recharge;
use App\Services\UiService;
use App\TraitClass\ChannelTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use Illuminate\Support\Facades\DB;

class RechargeController extends BaseCurlIndexController
{
    use ChannelTrait,MemberCardTrait,PayTrait;

//    public $pageName = '充值';
    public array $forward = [
        0=>[
            'id'=>'',
            'name'=>'全部'
        ],
        1=>[
            'id'=>1,
            'name'=>'我的'
        ],
        2=>[
            'id'=>2,
            'name'=>'长视频'
        ],
        3=>[
            'id'=>3,
            'name'=>'小视频'
        ],
        4=>[
            'id'=>4,
            'name'=>'直播'
        ],
        5=>[
            'id'=>5,
            'name'=>'社区'
        ],
    ];

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
        ],
        3=>[
            'id'=>3,
            'name'=>'ios轻量版'
        ],
    ];

    public function setModel()
    {
        return $this->model = new Recharge();
    }

    public function indexCols(): array
    {
        return [
            [
                'type' => 'checkbox',
                'fixed' => 'left'
            ],
            [
                'field' => 'id',
                'width' => 80,
                'title' => '编号',
                'sort' => 1,
                'totalRowText' => '合计',
                'align' => 'center'
            ],
            [
                'field' => 'channel_id',
                'minWidth' => 100,
                'title' => '推广渠道',
                'align' => 'center'
            ],
            [
                'field' => 'channel_principal',
                'minWidth' => 100,
                'title' => '渠道负责人',
                'align' => 'center'
            ],
            [
                'field' => 'forward',
                'width' => 100,
                'title' => '充值来源',
                'align' => 'center'
            ],
            [
                'field' => 'uid',
                'width' => 100,
                'title' => '会员ID',
                'align' => 'center'
            ],
            [
                'field' => 'order_id',
                'minWidth' => 100,
                'title' => '订单ID',
                'align' => 'center'
            ],
            [
                'field' => 'amount',
                'minWidth' => 100,
                'title' => '金额',
                'totalRow' => true,
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
                'field' => 'pay_method_name',
                'minWidth' => 100,
                'title' => '充值类型',
                'align' => 'center'
            ],
            [
                'field' => 'channel_code',
                'minWidth' => 80,
                'title' => '充值渠道',
                'align' => 'center',
            ],
            [
                'field' => 'register_at',
                'width' => 150,
                'title' => '会员注册时间',
                'align' => 'center'
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center'
            ],

            /*[
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]*/
        ];
    }

    public function setListOutputItemExtend($item)
    {
        $item->channel_id = isset($this->getChannelSelectData(true)[$item->channel_id]) ? $this->getChannelSelectData(true)[$item->channel_id]['name'] : '该渠道被删除';
        if($item->type!=1){
            $item->type = '金币';
        }else{
            $remark = @json_decode($item->remark,true);
            $item->type = $remark['name'] ?? '';
        }
        $item->status = UiService::switchTpl('status', $item,'','完成|未付');
        $item->forward = match ($item->forward) {
            'video' => '长视频',
            'short' => '短视频',
            'live' => '直播',
            'community' => '社区',
            default => '我的',
        };
        $item->device_system = $this->deviceSystem[$item->device_system]['name'];
        $payChannels = $this->getPayChannels();
        $item->pay_method_name = $payChannels[$item->pay_method]??'-';
        return $item;
    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            /*[
                'field' => 'channel_id',
                'type' => 'select',
                'name' => '选择渠道',
                'data' => $this->getChannelSelectData()
            ],*/
            [
                'field' => 'channel_id_tree',
                'type' => 'select',
                'name' => '顶级渠道',
                'default' => '',
                'data' => $this->getTopChannels()
            ],
            [
                'field' => 'channel_id',
                'type' => 'select',
                'name' => '所有渠道',
                'default' => '',
                'data' => $this->getAllChannels()
            ],
            [
                'field' => 'type',
                'type' => 'select',
                'name' => '订单类型',
                'data' => $this->getMemberCardList(),
            ],
            [
                'field' => 'forward',
                'type' => 'select',
                'name' => '充值来源',
                'data' => $this->forward,
            ],
            [
                'field' => 'device_system',
                'type' => 'select',
                'name' => '手机系统平台',
                'data' => $this->deviceSystem
            ],
            [
                'field' => 'query_uid',
                'type' => 'text',
                'name' => '会员ID',
            ],
            [
                'field' => 'query_pay_method',
                'type' => 'select',
                'name' => '充值类型',
                'data' => $this->getAllPayChannel()
            ],
            [
                'field' => 'query_channel_code',
                'type' => 'select',
                'name' => '充值渠道',
                'data' => array_merge(['0'=>['id'=>'0','name'=>'全部']],$this->getPayTypeCode())
            ],
            [
                'field' => 'query_channel_principal',
                'type' => 'text',
                'name' => '渠道负责人',
            ],
            [
                'field' => 'register_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '会员注册时间',
            ],
            [
                'field' => 'created_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '创建时间',
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function handleResultModel($model)
    {
        $field = ['recharge.id','recharge.amount','recharge.uid','recharge.order_id','orders.status',
            'recharge.channel_id','recharge.device_system','recharge.created_at','users.created_at as register_at','orders.type','orders.forward','orders.vid','orders.type_id','orders.remark','recharge.pay_method','recharge.channel_principal','recharge.channel_code'];
        $raw = implode(',',$field);
        $model = $model->select(DB::raw($raw));

        $type = $this->rq->input('type',null);
        $forward = $this->rq->input('forward',null);
        $channel_id = $this->rq->input('channel_id',null);
        $topChannelId = $this->rq->input('channel_id_tree',null);
        $queryUid = $this->rq->input('query_uid',0);
        $created_at = $this->rq->input('created_at',null);
        $register_at = $this->rq->input('register_at',null);
        $deviceSystem = $this->rq->input('device_system',null);
        $reqChannelPrincipal = $this->rq->input('query_channel_principal', null);
        //
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);
        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);
        $build = $model
            ->join('orders','recharge.order_id','=','orders.id')
            ->join('users','recharge.uid','=','users.id')
        ;
        if($queryUid>0){
            $build = $build->where('recharge.uid',$queryUid);
        }
        if($channel_id!==null){
            $build = $build->where('recharge.channel_id',$channel_id);
        }
        if($deviceSystem!==null){
            $build = $build->where('recharge.device_system',$deviceSystem);
        }
        if($reqChannelPrincipal!==null){
            $build = $build->where('recharge.channel_principal','like','%'.$reqChannelPrincipal);
        }
        if($topChannelId!==null){
            $build = $build->where(function ($build) use ($topChannelId){
                $build->where('recharge.channel_id',$topChannelId)
                    ->orWhere('recharge.channel_pid',$topChannelId);
            });
        }
        if($register_at!==null){
            $dateArr = explode('~',$register_at);
            if(isset($dateArr[0]) && isset($dateArr[1])){
                $build = $build->whereBetween('users.created_at', [trim($dateArr[0]),trim($dateArr[1])]);
            }
        }
        if($created_at!==null){
            $dateArr = explode('~',$created_at);
            if(isset($dateArr[0]) && isset($dateArr[1])){
                $build = $build->whereBetween('recharge.created_at', [trim($dateArr[0]),trim($dateArr[1])]);
            }
        }
        if($type!==null){
            if($type == 0){
                $build = $build->where('orders.type',2);
            }else{
                $build = $build->where('orders.type',1)->where('orders.type_id',$type);
            }
        }
        if($forward!==null){
            $forward += 0;
            $build = match ($forward) {
                5 => $build->where('orders.forward', 'community'),
                4 => $build->where('orders.forward', 'live'),
                3 => $build->where('orders.forward', 'short'),
                2 => $build->where('orders.forward', 'video'),
                1 => $build->where('orders.forward', '')
            };
        }

        $queryPayMethod = $this->rq->input('query_pay_method',0);
        if($queryPayMethod>0){
            $build = $build->where('recharge.pay_method',$queryPayMethod);
        }
        $queryChannelCode = $this->rq->input('query_channel_code',0);
        if($queryChannelCode>0){
            $build = $build->where('recharge.channel_code',$queryChannelCode);
        }

        $totalAmount = $build->sum('recharge.amount');

        $total = $build->count();

        $currentPageData = $build->forPage($page, $pagesize)->get($field);
//        $this->listOutputJson($total, $currentPageData, 0);
        return [
            'total' => $total,
            'totalRow' => ['amount'=>$totalAmount],
            'result' => $currentPageData
        ];
    }

    //首页共享数据
    public function indexShareData()
    {
        //设置首页数据替换
        $this->setListConfig(['open_width' => '600px', 'open_height' => '700px','tableConfig' => ['totalRow' => true]]);
    }

}