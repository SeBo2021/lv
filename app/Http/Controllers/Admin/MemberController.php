<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberCard;
use App\Models\User;
use App\Services\UiService;
use App\TraitClass\ChannelTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PayTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MemberController extends BaseCurlController
{
    use ChannelTrait,MemberCardTrait,PayTrait;
    //设置页面的名称
    public $pageName = '会员';

//    public $denyCommonBladePathActionName = ['index','create','edit'];

    //1.设置模型
    public function setModel(): User
    {
        return $this->model = new User();
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
                'minWidth' => 100,
                'title' => '编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'promotion_code',
                'minWidth' => 100,
                'title' => '推广码',
                'hide' => true,
                'align' => 'center'
            ],
            /*[
                'field' => 'mid',
                'width' => 100,
                'title' => '会员ID',
                'sort' => 1,
                'align' => 'center'
            ],*/
            [
                'field' => 'pid',
                'minWidth' => 100,
                'title' => '上级ID',
                'sort' => 1,
                'hide' => true,
                'align' => 'center',
                //'edit' => 1
            ],
            [
                'field' => 'channel_id',
                'minWidth' => 150,
                'title' => '推广渠道',
                'align' => 'center',
            ],
            [
                'field' => 'account',
                'minWidth' => 150,
                'title' => '账号',
                'align' => 'center',
            ],
            [
                'field' => 'nickname',
                'minWidth' => 150,
                'title' => '昵称',
                'align' => 'center',
            ],
            [
                'field' => 'location_name',
                'minWidth' => 150,
                'title' => '最近登录位置',
                'align' => 'center',
            ],
            [
                'field' => 'phone_number',
                'minWidth' => 150,
                'title' => '手机号',
                'align' => 'center',
            ],
            [
                'field' => 'member_card_type',
                'minWidth' => 80,
                'title' => 'VIP',
                'align' => 'center'
            ],
            [
                'field' => 'vip_start_last',
                'minWidth' => 80,
                'title' => 'vip最近开通时间',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'vip_expired',
                'minWidth' => 80,
                'title' => 'vip过期时间',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'gold',
                'minWidth' => 80,
                'title' => '金币',
                'align' => 'center'
            ],
            [
                'field' => 'long_vedio_times',
                'minWidth' => 80,
                'title' => '可观看次数',
                'align' => 'center'
            ],
            [
                'field' => 'did',
                'minWidth' => 150,
                'title' => '机器码',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'create_ip',
                'minWidth' => 150,
                'title' => '注册IP',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'last_ip',
                'minWidth' => 150,
                'title' => '最近IP',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'systemPlatform',
                'minWidth' => 150,
                'title' => '手机系统平台',
                'align' => 'center',
            ],
            [
                'field' => 'login_numbers',
                'minWidth' => 80,
                'title' => '登录次数',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 170,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                //'fixed' => 'right',
                'align' => 'center'
            ]
        ];
    }

    public function setOutputSearchFormTpl($shareData)
    {

        $data = [
            /*[
                'field' => 'query_channel_id',
                'type' => 'select',
                'name' => '选择渠道',
                'data' => $this->getChannelSelectData()
            ],*/
            [
                'field' => 'query_channel_id_tree',
                'type' => 'select',
                'name' => '顶级渠道',
                'default' => '',
                'data' => $this->getTopChannels()
            ],
            [
                'field' => 'query_channel_id',
                'type' => 'select',
                'name' => '所有渠道',
                'default' => '',
                'data' => $this->getAllChannels()
            ],
            [
                'field' => 'query_phone_number',
                'type' => 'select',
                'name' => '是否绑定',
                'data' => $this->bindPhoneNumSelectData
            ],
            [
                'field' => 'query_member_card_type',
                'type' => 'select',
                'name' => 'VIP',
                'data' => $this->getMemberCardList('gold')
            ],
            [
                'field' => 'query_gold',
                'type' => 'select',
                'name' => '骚豆',
                'data' => [
                    ''=>[
                        'id' => '',
                        'name' => '全部',
                    ],1=>[
                        'id' => 1,
                        'name' => '1-99',
                    ],2=>[
                        'id' => 2,
                        'name' => '100-999',
                    ],3=>[
                        'id' => 3,
                        'name' => '1000以上',
                    ],
                ]
            ],
            [
                'field' => 'query_long_vedio_times',
                'type' => 'select',
                'name' => '可观看次数',
                'data' => [
                    ''=>[
                        'id' => '',
                        'name' => '全部',
                    ],0=>[
                        'id' => 0,
                        'name' => '0次',
                    ],1=>[
                        'id' => 1,
                        'name' => '1次',
                    ],2=>[
                        'id' => 2,
                        'name' => '2次',
                    ],3=>[
                        'id' => 3,
                        'name' => '3次',
                    ],
                ]
            ],
            [
                'field' => 'query_device_system',
                'type' => 'select',
                'name' => '客户端来源',
                'data' => $this->deviceSystemsSelect
            ],
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '会员ID',
            ],
            [
                'field' => 'query_like_account',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '账号',
            ],
            [
                'field' => 'query_created_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '时间范围',
            ],
            [
                'field' => 'query_status',
                'type' => 'select',
                'name' => '是否启用',
                'default' => '',
                'data' => $this->uiService->trueFalseData(1)
            ],
            [
                'field' => 'query_did',
                'type' => 'text',
                'name' => '机器码',
            ],

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'account',
                'type' => 'text',
                'name' => '账号',
                'must' => 0,
                'verify' => 'rq',
            ],
            /*[
                'field' => 'avatar',
                'type' => 'img',
                'name' => '头像',
                'verify' => $show ? '' : 'rq',
            ],*/
            [
                'field' => 'nickname',
                'type' => 'text',
                'name' => '昵称',
                'must' => 0,
                'verify' => 'rq',
            ],
            [
                'field' => 'long_vedio_times',
                'type' => 'number',
                'name' => '可观看次数',
            ],
            [
                'field' => 'gold',
                'type' => 'number',
                'name' => '骚豆',
            ],
            [
                'field' => 'vipCards',
                'type' => 'checkbox',
                'name' => '会员卡',
                'verify' => '',
                'value' => ($show && ($show->member_card_type)) ? explode(',',$show->member_card_type) : [],
                'data' => $this->getMemberCardList('default')
            ],
            [
                'field' => 'is_office',
                'type' => 'radio',
                'name' => '是否官方',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],
            [
                'field' => 'location_name',
                'type' => 'text',
                'name' => '用户地址',
            ],
            [
                'field' => 'password',
                'type' => 'text',
                'name' => '密码',
                'must' => 1,
                'verify' => $show ? '' : 'rq',
                // 'remove'=>$show?'1':0,//1表示移除，编辑页面不出现
                'value' => '',
                'mark' => $show ? '不填表示不修改密码' : '',
            ],
            [
                'field' => 'did',
                'type' => 'text',
                'name' => '设备码',
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->systemPlatform = $this->deviceSystems[$item->device_system];
        $item->channel_id = isset($this->getChannelSelectData(true)[$item->channel_id]) ? $this->getChannelSelectData(true)[$item->channel_id]['name'] : '该渠道被删除';
        //$item->area = DB::table('login_log')->where('uid',$item->id)->orderByDesc('id')->value('area');
        $item->status = UiService::switchTpl('status', $item,'');
        $item->phone_number = $item->phone_number>0 ? $item->phone_number : '未绑定';
        $item->member_card_type = $this->getMemberCardList('gold')[max(explode(',',$item->member_card_type))]['name'] ?? '';
        $item->vip_start_last = date('Y-m-d H:i:s',$item->vip_start_last);
        $item->vip_expired = $item->vip_expired>0 ? round($item->vip_expired/3600).'小时' :0;
        return $item;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        if($id > 0){
            $cards = $this->rq->input('vipCards',[]);
            $member_card_type = implode(',',$cards);
            $originalData = $model->getOriginal();
            if($member_card_type != $originalData['member_card_type']){ //如果有变更会员卡信息
                $model->member_card_type = $member_card_type;
                $model->vip_start_last = $member_card_type ? time() : 0;
                $model->vip_expired = MemberCard::query()->select(DB::raw('SUM(IF(expired_hours>0,expired_hours,10*365*24)) as expired_hours'))->whereIn('id',$cards)->value('expired_hours') *3600;
                $model->vip = !empty($cards) ? max($cards) : 0;
            }
        }
    }

    public function afterSaveSuccessEvent($model, $id)
    {
        Cache::forget("cachedUser.{$id}");
        return $model;
    }

    public function handleResultModel($model): array
    {
        $memberCard = $this->rq->input('query_member_card_type', null);
        $viewTimes = $this->rq->input('query_long_vedio_times', null);
        $reqGolds = $this->rq->input('query_gold', null);
        $reqDid = $this->rq->input('query_did', null);
        $reqDeviceSystem = $this->rq->input('query_device_system', null);
        if($reqDeviceSystem!==null){
            $model = $model->where('device_system',$reqDeviceSystem);
        }
        if($reqDid!==null){
            $model = $model->where('did',$reqDid);
        }
        if($viewTimes!==null){
            $model = $model->where('long_vedio_times',$viewTimes);
        }
        if($memberCard!==null){
            $model = $model->where('member_card_type','!=','')->whereRaw('member_card_type' . ' like ?', ["%" . $memberCard]);
        }
        if($reqGolds!==null){
            switch ($reqGolds){
                case 1:
                    $model = $model->whereBetween('gold',[1,99]);
                    break;
                case 2:
                    $model = $model->whereBetween('gold',[100,999]);
                    break;
                case 3:
                    $model = $model->where('gold','>=',1000);
                    break;
            }
        }
        return parent::handleResultModel($model);
    }

    public function setOutputHandleBtnTpl($shareData): array
    {
        /*if ($this->isCanCreate()) {

            $data[] = [
                'name' => '添加',
                'data' => [
                    'data-type' => "add"
                ]
            ];
        }
        if ($this->isCanDel()) {
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '删除',
                'data' => [
                    'data-type' => "allDel"
                ]
            ];
        }*/

        return [];
    }

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '55%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }
}
