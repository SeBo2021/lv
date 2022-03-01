<?php

namespace App\Http\Controllers\Admin;

use App\Models\Channel;
use App\Services\UiService;
use App\TraitClass\ChannelTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CpsChannelsController extends BaseCurlController
{
    use ChannelTrait;
    public $pageName = '渠道';

    public array $isDeduction = [
        1 => ['id' => 1, 'name' => '开'],
        0 => ['id' => 0, 'name' => '关'],
    ];

    public array $channelType = [
        0 => [
            'id' => 0,
            'name' => 'CPA'
        ],
        1 => [
            'id' => 1,
            'name' => '包月'
        ],
        2 => [
            'id' => 2,
            'name' => 'CPS'
        ],
    ];

    public function setModel(): Channel
    {
        return $this->model = new Channel();
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
                'field' => 'type',
                'minWidth' => 100,
                'title' => '渠道类型',
                'align' => 'center'
            ],
            [
                'field' => 'principal',
                'minWidth' => 100,
                'title' => '负责人',
                'align' => 'center'
            ],
            [
                'field' => 'name',
                'minWidth' => 100,
                'title' => '渠道名称',
                'align' => 'center'
            ],
            [
                'field' => 'promotion_code',
                'minWidth' => 100,
                'title' => '推广码',
                //'edit' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'deduction',
                'minWidth' => 100,
                'title' => '扣量(点)',
                //'edit' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'number',
                'minWidth' => 80,
                'title' => '渠道码',
//                'hide' => true,
                'align' => 'center',
            ],
            [
                'field' => 'url',
                'minWidth' => 80,
                'title' => '渠道推广链接',
                'align' => 'center',
            ],
            [
                'field' => 'fast_url',
                'minWidth' => 150,
                'title' => '渠道直推下载安装链接',
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
                'field' => 'updated_at',
                'minWidth' => 150,
                'title' => '更新时间',
                'hide' => true,
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

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'principal',
                'type' => 'text',
                'name' => '负责人',
                'must' => 1,
                'default' => '',
            ],
            [
                'field' => 'password',
                'type' => 'text',
                'name' => '密码',
                // 'remove'=>$show?'1':0,//1表示移除，编辑页面不出现
                'value' => '',
                'mark' => $show ? '不填表示不修改密码' : '',
            ],
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '渠道名称',
                'must' => 1,
                'default' => '',
            ],
            [
                'field' => 'promotion_code',
                'type' => 'text',
                'name' => '推广码',
                'must' => 1,
            ],
            /*[
                'field' => 'type',
                'type' => 'radio',
                'name' => '类型',
                'must' => 0,
                'default' => 0,
                'verify' => 'rq',
                'data' => $this->channelType
            ],*/
            [
                'field' => 'deduction',
                'type' => 'number',
                'name' => '扣量(点)',
                'value' => ($show && ($show->deduction>0)) ? $show->deduction/100 : 0,
                'must' => 0,
                'default' => 50,
            ],
            [
                'field' => 'unit_price',
                'type' => 'text',
                'name' => '单价',
                'must' => 0,
            ],
            [
                'field' => 'is_deduction',
                'type' => 'radio',
                'name' => '前10个下载不扣量',
                'default' => 1,
                'data' => $this->isDeduction
            ],
            /*[
                'field' => 'deduction_period',
                'type' => 'text',
                'event' => 'timeRange',
                'name' => '扣量时间段 (CPS使用)',
                'must' => 0,
                'attr' => 'data-format=HH:mm:ss data-range=~',//需要特殊分割
                'default' => '00:00:00 ~ 23:59:59',
            ],*/
            [
                'field' => 'level_one',
                'type' => 'text',
                'name' => '一阶 (CPS使用)',
                'tips' => '1-30单【填1表示扣第1单，如填1,3表示扣第一单和第三单，不填不扣】',
            ],
            [
                'field' => 'level_two',
                'type' => 'number',
                'name' => '二阶 (CPS使用)',
                'tips' => '31单及以上【填间隔值：填1，表示累计第31,33,35..类推，2表示只累计第31,34,37..】',
            ],
            [
                'field' => 'share_ratio',
                'type' => 'number',
                'name' => '分成比例 (CPS使用)',
                'default' => '',
            ],
        ];
        //赋值给UI数组里面,必须是form为key
        $this->uiBlade['form'] = $data;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $this->beforeSaveEventHandle($model, $id, 2);
    }

    public function afterSaveSuccessEvent($model, $id = '')
    {
        $this->afterSaveSuccessEventHandle($model, $id);
        return $model;
    }

    public function editTable(Request $request)
    {
        $this->editTableHandle($request);
    }

    public function setListOutputItemExtend($item)
    {
        $item->deduction /= 100;
        $item->status = UiService::switchTpl('status', $item,'');
        $item->type = $this->channelType[$item->type]['name'];
        return $item;
    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            [
                'field' => 'id',
                'type' => 'select',
                'name' => '选择CPS渠道',
                'data' => $this->getAllChannels(2)
            ],
            [
                'field' => 'query_status',
                'type' => 'select',
                'name' => '状态',
                'default' => 1,
                'data' => [
                    '' => [
                        'id'=>'',
                        'name' => '全部'
                    ],0 => [
                        'id'=>0,
                        'name' => '禁用'
                    ],1 => [
                        'id'=>1,
                        'name' => '启用'
                    ],
                ]
            ]

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    //表单验证
    public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:channels,name',
            'principal'=>'required',
            'promotion_code'=>'required|unique:channels,promotion_code',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['password'] = '';
            $data['name'] = 'required|unique:channels,name,' . $id;
            $data['promotion_code'] = 'required|unique:channels,promotion_code,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'principal'=>'负责人',
            'name'=>'渠道名称',
            'promotion_code'=>'推广码',
        ];
    }

    public function handleResultModel($model): array
    {
        $model = $model->where('type',2);
        if(!isset($_REQUEST['query_status'])){
            $model = $model->where('status',1);
        }
        return parent::handleResultModel($model);
    }

    //弹窗大小
    public function layuiOpenWidth(): string
    {
        return '55%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight(): string
    {
        return '75%'; // TODO: Change the autogenerated stub
    }
}