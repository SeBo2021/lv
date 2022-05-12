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
                'fixed' => 'left',
                'align' => 'center'
            ],
            [
                'field' => 'channel_name',
                'minWidth' => 150,
                'title' => '推广渠道',
                'fixed' => 'left',
                'align' => 'center',
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
                'field' => 'account',
                'minWidth' => 150,
                'title' => '账号',
                'align' => 'center',
            ],
            [
                'field' => 'channel_principal',
                'minWidth' => 150,
                'title' => '渠道负责人',
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
                'field' => 'area_number',
                'minWidth' => 150,
                'title' => '国际码',
                'align' => 'center',
                'hide' => true,
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
                'title' => '次数',
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
                'minWidth' => 100,
                'title' => '状态',
                'align' => 'center',
            ],
            [
                'field' => 'app_info',
                'minWidth' => 100,
                'title' => '应用信息',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 170,
                'title' => '创建时间',
                'align' => 'center'
            ],
            [
                'field' => 'updated_at',
                'minWidth' => 170,
                'title' => '更新时间',
                'align' => 'center'
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'fixed' => 'right',
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
                'field' => 'query_status',
                'type' => 'select',
                'name' => '是否启用',
                'default' => '',
                'data' => $this->uiService->trueFalseData(1)
            ],
            [
                'field' => 'find_phone_number',
                'type' => 'text',
                'name' => '手机号',
            ],
            [
                'field' => 'query_like_account',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '账号',
            ],
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '会员ID',
            ],
            [
                'field' => 'query_did',
                'type' => 'text',
                'name' => '机器码',
            ],
            [
                'field' => 'query_channel_principal',
                'type' => 'text',
                'name' => '渠道负责人',
            ],
            [
                'field' => 'query_created_at',
                'type' => 'datetime',
//                'attr' => 'data-range=true',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '时间范围',
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
            [
                'field' => 'area_number',
                'type' => 'text',
                'name' => '国际码',
                'must' => 0,
                'verify' => 'rq',
            ],
            [
                'field' => 'phone_number',
                'type' => 'text',
                'name' => '手机号',
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
        //$item->area = DB::table('login_log')->where('uid',$item->id)->orderByDesc('id')->value('area');
        $item->status = UiService::switchTpl('status', $item,'');
        $item->phone_number = $item->phone_number>0 ? $item->phone_number : '未绑定';
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
        Cache::forget('cachedUser.'.$id);
        return $model;
    }

    public function handleResultModel($model): array
    {
        $memberCard = $this->rq->input('query_member_card_type', null);
        $viewTimes = $this->rq->input('query_long_vedio_times', null);
        $reqGolds = $this->rq->input('query_gold', null);
        $reqDid = $this->rq->input('query_did', null);
        $reqDeviceSystem = $this->rq->input('query_device_system', null);
        $findPhoneNumber = $this->rq->input('find_phone_number', null);
        $reqChannelPrincipal = $this->rq->input('query_channel_principal', null);
        if($findPhoneNumber!==null){
            $model = $model->where('phone_number',$findPhoneNumber);
        }
        if($reqChannelPrincipal!==null){
            $ids = DB::table('channels')
                ->where('principal','like','%'.$reqChannelPrincipal)
                ->pluck('id')->all();
//            dump($ids);
            $model = $model->whereIn('channel_id',$ids);
        }
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

        $channels = DB::table('channels')->get(['id','name','principal']);
        $channelCollection = [];
        foreach ($channels as $channel){
            $channelCollection[$channel->id] = $channel;
        }

        $memberCardTypes = $this->getMemberCardList('gold');

        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);
        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);
        $total = $model->count();
        $result = $model->forPage($page, $pagesize)->get();

        foreach ($result as $item){
            $item->channel_name = $channelCollection[$item->channel_id]->name ?? '官方';
            $item->channel_principal = $channelCollection[$item->channel_id]->principal ?? '';
            $item->member_card_type = $memberCardTypes[max(explode(',',$item->member_card_type))]['name'] ?? '';
        }
        return [
            'total' => $total,
            'result' => $result
        ];
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
