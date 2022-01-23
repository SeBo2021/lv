<?php


namespace App\Http\Controllers\Admin;


use App\Models\Recharge;
use App\Services\UiService;
use App\TraitClass\ChannelTrait;
use Illuminate\Support\Facades\DB;

class RechargeController extends BaseCurlIndexController
{
    use ChannelTrait;

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
        if($item->type == 1){
            $remark = json_decode(DB::table('orders')->where('id',$item->order_id)->value('remark'),true);
            $item->type = $remark['name'] ?? '';
        }else{
            $item->type = '金币';
        }

        $item->device_system = $this->deviceSystem[$item->device_system]['name'];
        return $item;
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
                'field' => 'query_type',
                'type' => 'select',
                'name' => '订单类型',
                'data' => $this->orderType,
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

}