<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessStatisticsChannelCps;
use App\Models\Order;
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
    public function setModel()
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
                'edit' => 1
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
}