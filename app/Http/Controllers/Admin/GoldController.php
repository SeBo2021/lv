<?php

namespace App\Http\Controllers\Admin;

use App\Models\Gold;
use App\Models\RechargeChannel;
use App\Services\UiService;
use App\TraitClass\PayTrait;
use App\TraitClass\PHPRedisTrait;

class GoldController extends BaseCurlController
{
    use PayTrait;
    use PHPRedisTrait;
    //设置页面的名称
    public $pageName = '骚豆设置';

    //1.设置模型
    public function setModel()
    {
        return $this->model = new Gold();
    }

    public function indexCols()
    {
        $cols = [
            [
                'type' => 'checkbox'
            ],
            [
                'field' => 'id',
                'minWidth' => 80,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'sort',
                'minWidth' => 80,
                'title' => '排序',
                'sort' => 1,
                'edit' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'money',
                'minWidth' => 100,
                'title' => '展示金额',
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'zfb_action_name',
                'minWidth' => 100,
                'title' => '支付宝充值方式',
                'align' => 'center',
            ],
            [
                'field' => 'zfb_channel',
                'minWidth' => 100,
                'title' => '支付宝通道',
                'align' => 'center',
            ],
            [
                'field' => 'zfb_fee',
                'minWidth' => 100,
                'title' => '支付宝金额',
                'align' => 'center',
            ],
            [
                'field' => 'wx_action_name',
                'minWidth' => 100,
                'title' => '微信充值方式',
                'align' => 'center',
            ],
            [
                'field' => 'wx_channel',
                'minWidth' => 100,
                'title' => '微信通道',
                'align' => 'center',
            ],
            [
                'field' => 'wx_fee',
                'minWidth' => 100,
                'title' => '微信金额',
                'align' => 'center',
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
                'align' => 'center',
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

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'money',
                'type' => 'text',
                'name' => '展示金额',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'zfb_action_id',
                'minWidth' => 100,
                'name' => '支付宝充值方式',
                'type' => 'select',
                'data' => $this->getPayChannelData()
            ],
            [
                'field' => 'zfb_channel',
                'minWidth' => 100,
                'name' => '支付宝渠道',
                'type' => 'select',
                'data' => $this->getPayTypeCode()
            ],
            [
                'field' => 'zfb_fee',
                'minWidth' => 100,
                'name' => '支付宝金额',
                'type' => 'number',
                'align' => 'center',
            ],
            [
                'field' => 'wx_action_id',
                'minWidth' => 100,
                'name' => '微信充值方式',
                'type' => 'select',
                'data' => $this->getPayChannelData()
            ],
            [
                'field' => 'wx_channel',
                'minWidth' => 100,
                'name' => '微信通道',
                'type' => 'select',
                'data' => $this->getPayTypeCode()
            ],
            [
                'field' => 'wx_fee',
                'minWidth' => 100,
                'name' => '微信金额',
                'type' => 'number',
                'align' => 'center',
            ],
            [
                'field' => 'sort',
                'type' => 'number',
                'name' => '排序',
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '状态',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,'');
        return $item;
    }

    public function getPayChannelData()
    {
        $res = RechargeChannel::query()
            ->where('status',1)
            ->get(['id','name','remark']);
        $data = $this->uiService->allDataArr('请选择支付方式');
        foreach ($res as $item) {
            $data[$item->id] = [
                'id' => $item->id,
                'name' => $item->remark,
            ];
        }
        return $data;
    }

    /**
     * 清理缓存
     * @param $model
     * @param string $id
     * @return mixed
     */
    protected function afterSaveSuccessEvent($model, $id = '')
    {
        //清除缓存
        $this->redis()->del('api_recharge_member_gold');
        return $model;
    }
}