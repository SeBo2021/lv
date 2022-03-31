<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessStatisticsChannelByDay;
use App\Models\Order;
use App\Models\RechargeChannel;
use App\Services\UiService;
use App\TraitClass\PayTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends BaseCurlController
{
    use PayTrait;
    //设置页面的名称
    public $pageName = '订单';

    //1.设置模型
    public function setModel(): Order
    {
        return $this->model = new Order();
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
                'field' => 'uid',
                'minWidth' => 80,
                'title' => '用户',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'channel_id',
                'minWidth' => 80,
                'title' => '渠道',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'number',
                'width' => 150,
                'title' => '订单编号',
                'align' => 'center',
            ],
            [
                'field' => 'amount',
                'width' => 150,
                'title' => '订单金额',
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'type',
                'minWidth' => 80,
                'title' => '订单类型',
                'align' => 'center',
            ],
            [
                'field' => 'remark',
                'minWidth' => 150,
                'title' => '备注',
                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
                'align' => 'center',
            ],
            [
                'field' => 'pay_method_name',
                'minWidth' => 80,
                'title' => '充值类型',
                'align' => 'center',
            ],
            [
                'field' => 'channel_code',
                'minWidth' => 80,
                'title' => '充值渠道',
                'align' => 'center',
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

    public function setOutputHandleBtnTpl($shareData)
    {
        $this->uiBlade['btn'] = [];
    }

    public function setListOutputItemExtend($item)
    {
        $types = [
            1 => '会员卡',
            2 => '骚豆',
        ];
        $item->type = $types[$item->type];
        //$item->amount = round($item->amount/100,2);
        $item->status = UiService::switchTpl('status', $item,'','完成|未付');
        $channel_name = $item->channel_id>0 ? DB::table('channels')->where('id',$item->channel_id)->value('name') : '官方';
        $item->channel_id = $channel_name . '('.$item->channel_id.')';
        $item->pay_method_name = match (strval($item->pay_method)) {
            '2' => '长江支付',
            '4' => 'YK支付',
            '1' => '大白鲨支付',
            '101' => '信达支付',
            '102' => '艾希支付',
            default => '大白鲨支付',
        };
        return $item;
    }

    public function editTable(Request $request)
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
            $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
            if ($r) {
                if($field=='status'){
                    if($value == 0){
                        return $this->returnFailApi(lang('订单已手动完成'));
                    }
                    $tradeNo = $this->model->whereIn($id, $id_arr)->value('number');
                    $this->orderUpdate($tradeNo);
                    $this->insertLog($this->getPageName() . lang('手动完成订单成功') . '：' . implode(',', $id_arr));
                }
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }

    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            [
                'field' => 'query_uid',
                'type' => 'text',
                'name' => '会员ID',
            ],
            [
                'field' => 'status',
                'type' => 'select',
                'name' => '状态',
                'default' => '',
                'data' => [
                    ''=>[
                        'id'=>'',
                        'name'=>'全部',
                    ],0=>[
                        'id'=>0,
                        'name'=>'未付',
                    ],1=>[
                        'id'=>1,
                        'name'=>'完成',
                    ],
                ]
            ],
            [
                'field' => 'created_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '时间范围',
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
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function handleResultModel($model): array
    {
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);

        $field = ['orders.id', 'orders.remark', 'orders.number', 'orders.forward', 'orders.type', 'orders.vid', 'orders.type_id', 'orders.channel_pid', 'orders.channel_id', 'orders.uid', 'orders.amount', 'orders.status', 'orders.created_at', 'orders.updated_at', 'orders.expired_at', 'recharge.pay_method', 'recharge.channel_code'];
        $raw = implode(',',$field);
        $model = $model->select(DB::raw($raw));

        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);

        $build = $model
            ->leftJoin('recharge','orders.id','=','recharge.order_id');

        $queryPayMethod = $this->rq->input('query_pay_method',0);
        if($queryPayMethod>0){
            $build = $build->where('recharge.pay_method',$queryPayMethod);
        }
        $queryChannelCode = $this->rq->input('query_channel_code',0);
        if($queryChannelCode>0){
            $build = $build->where('recharge.channel_code',$queryChannelCode);
        }
        $queryUid = $this->rq->input('query_uid',0);
        if($queryUid>0){
            $build = $build->where('orders.uid',$queryUid);
        }
        $queryStatus = $this->rq->input('status','');
        if($queryStatus != ''){
            $build = $build->where('orders.status',$queryStatus);
        }
        $created_at = $this->rq->input('created_at',null);
        if($created_at!==null){
            $dateArr = explode('~',$created_at);
            if(isset($dateArr[0]) && isset($dateArr[1])){
                $build = $build->whereBetween('orders.created_at', [trim($dateArr[0]),trim($dateArr[1])]);
            }
        }

        $total = $build->count();

        $currentPageData = $build->forPage($page, $pagesize)->get($field);
        $this->listOutputJson($total, $currentPageData, 0);
        return [
            'total' => $total,
            'result' => $currentPageData
        ];
    }

}