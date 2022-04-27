<?php


namespace App\Http\Controllers\Admin;


use App\Models\Ad;
use App\Models\AdSet;
use App\Services\UiService;
use App\TraitClass\AboutEncryptTrait;
use App\TraitClass\AdTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdController extends BaseCurlController
{
    use PHPRedisTrait,AboutEncryptTrait,MemberCardTrait,AdTrait;

    public $pageName = '广告';

    public function setModel(): Ad
    {
        return $this->model = new Ad();
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
                'minWidth' => 80,
                'title' => '广告类型',
                'align' => 'center',
            ],
            [
                'field' => 'set',
                'minWidth' => 150,
                'title' => '广告位置',
                'align' => 'center',
            ],
            [
                'field' => 'card_id',
                'minWidth' => 150,
                'title' => '会员卡',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '广告位标识',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'sort',
                'width' => 80,
                'title' => '排序',
                'sort' => 1,
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'position',
                'width' => 80,
                'title' => '位置',
                'sort' => 1,
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'weight',
                'minWidth' =>80,
                'title' => '权重',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'title',
                'minWidth' => 150,
                'title' => '标题',
                'align' => 'center',

            ],
            [
                'field' => 'img',
                'minWidth' => 100,
                'title' => '图片',
                'align' => 'center',

            ],
            [
                'field' => 'url',
                'minWidth' => 100,
                'title' => '跳转链接地址',
                'align' => 'center',

            ],
            [
                'field' => 'play_url',
                'minWidth' => 100,
                'title' => '播放地址',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '是否启用',
                'align' => 'center',
            ],
            [
                'field' => 'start_at',
                'minWidth' => 150,
                'title' => '开始时间',
                'align' => 'center'
            ],
            [
                'field' => 'end_at',
                'minWidth' => 150,
                'title' => '结束时间',
                'align' => 'center'
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '创建时间',
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

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item);
        $item->type = $item->type==1 ? '视频' : '图片(H5)';
        $item->set = AdSet::query()->where('id',$item->flag_id)->value('name');
        return $item;
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $adFlags = AdSet::query()->where('status',1)->get(['id','name'])->toArray();
        $data = [
            [
                'field' => 'type',
                'type' => 'radio',
                'name' => '广告类型',
                'verify' => '',
                'default' => 0,
                'data' => [
                    1=>[
                        'id' => '1',
                        'name' => '视频'
                    ],
                    2=>[
                        'id' => '2',
                        'name' => '图片(H5)'
                    ]
                ]
            ],
            [
                'field' => 'flag_id',
                'type' => 'select',
                'name' => '广告位置',
                'must' => 1,
                'default' => '',
                'data' => $adFlags
            ],
            /*[
                'field' => 'card_id',
                'type' => 'select',
                'name' => '会员卡设置',
                'must' => 0,
                'default' => '',
                'data' => $this->getMemberCardList('gold')
            ],*/
            [
                'field' => 'title',
                'type' => 'text',
                'name' => '标题',
                'must' => 0,
//                'verify' => 'rq',
                'default' => '',
            ],
            [
                'field' => 'img',
                'type' => 'img',
                'name' => '图片',
                'must' => 1,
                'value' => ($show && ($show->img)) ? VideoTrait::getDomain(env('SFTP_SYNC',1)).$show->img: ''
            ],
            [
                'field' => 'url',
                'type' => 'text',
                'name' => '跳转链接地址',
                'must' => 0,
                'default' => '',
            ],
            [
                'field' => 'action_type',
                'type' => 'text',
                'name' => '操作',
                'must' => 0,
                'tips' => '0-无操作,1-打开链接',
                'default' => '',
            ],
            [
                'field' => 'vid',
                'type' => 'text',
                'name' => '视频ID',
                'must' => 0,
                'default' => '',
            ],
            [
                'field' => 'time_period',
                'type' => 'datetime',
                'attr' => 'data-range=~',//需要特殊分割
                'name' => '选择投放时间',
            ],
            [
                'field' => 'position',
                'type' => 'number',
                'name' => '位置',
                'must' => 0,
                'default' => '',
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '是否启用',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],
            [
                'field' => 'play_url',
                'type' => 'text',
                'name' => '播放地址',
                'must' => 0,
                'default' => '',
            ],
            [
                'field' => 'sort',
                'type' => 'text',
                'name' => '排序',
                'must' => 0,
                'default' => '',
            ],
            [
                'field' => 'weight',
                'type' => 'number',
                'name' => '权重',
                'must' => 0,
                'default' => '',
                'tips' => '权重值设置在1~10范围的整数'
            ],

        ];
        //赋值给UI数组里面,必须是form为key
        $this->uiBlade['form'] = $data;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $timePeriod = $this->rq->input('time_period',null);
        if($timePeriod!==null){
            $dateArr = explode('~',$timePeriod);
            $startTime = trim($dateArr[0]);
            $endTime = trim($dateArr[1]);
            $model->start_at = $startTime;
            $model->end_at = $endTime;
        }
        $model->name = AdSet::query()->where('id',$model->flag_id)->value('flag');
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function afterSaveSuccessEvent($model, $id = '')
    {
        $coverImg = str_replace(VideoTrait::getDomain(env('SFTP_SYNC',1)),"",$model->img);
        $model->img = $coverImg;
        $model->save();
        $this->syncUpload($model->img);
        //清除首页列表缓存
        $this->redisBatchDel($this->redis()->keys($this->apiRedisKey['home_lists'] . '*'));
        //清除推荐缓存
        $this->redisBatchDel($this->redis()->keys('*searchRecommend*'));
        //清除更多页缓存
        $this->redisBatchDel($this->redis()->keys('*api_more_cid-page:*'));
        //清除配置缓存
        $this->redisBatchDel($this->redis()->keys('*api_config*'));
    }

    //表单验证
    public function checkRule($id = '')
    {
        return [
            'img'=>'required',
        ];
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'img'=>'图片',
        ];
    }

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '65%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

}
