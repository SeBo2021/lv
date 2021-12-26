<?php
namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessLive;
use App\Models\Category;
use App\Models\Live;
use App\Models\Video;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TagTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;

class LiveController extends BaseCurlController
{
    use VideoTrait,CatTrait,TagTrait,GoldTrait,PHPRedisTrait;

    public $pageName = '直播列表';


    public function setModel()
    {
        return $this->model = new Live();
    }

    public function indexCols()
    {
        $cols = [
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
                'field' => 'name',
                'minWidth' => 150,
                'title' => '直播名',
                'align' => 'center',
            ],
            [
                'field' => 'author',
                'minWidth' => 80,
                'title' => '主播名',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'age',
                'minWidth' => 80,
                'title' => '年龄',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'intro',
                'minWidth' => 80,
                'title' => '简介',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'sync',
                'minWidth' => 80,
                'title' => '专线',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'duration',
                'minWidth' => 150,
                'title' => '时长',
                'align' => 'center',
            ],
            [
                'field' => 'duration_seconds',
                'minWidth' => 150,
                'title' => '时长秒',
                'align' => 'center',
                'hide' => true,
            ],
            [
                'field' => 'cover_img',
                'minWidth' => 150,
                'title' => '封面图',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'url',
                'minWidth' => 150,
                'title' => '源视频',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'hls_url',
                'minWidth' => 80,
                'title' => 'hls地址',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'dash_url',
                'minWidth' => 80,
                'title' => 'dash地址',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'type',
                'minWidth' => 80,
                'title' => '视频类型',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '是否上架',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'sort' => 1,
                'minWidth' => 150,
                'title' => '创建时间',
                'align' => 'center',
            ],
            [
                'field' => 'updated_at',
                'sort' => 1,
                'minWidth' => 150,
                'title' => '更新时间',
                'align' => 'center',
                'hide' => true
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
                'field' => 'name',
                'type' => 'text',
                'name' => '直播名',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'author',
                'type' => 'text',
                'tips' => '输入包含标签词的内容即可,格式不限,如:#内射#口交#人妻...',
                'name' => '主播名'
            ],
            [
                'field' => 'age',
                'type' => 'text',
                'tips' => '输入包含标签词的内容即可,格式不限,如:#内射#口交#人妻...',
                'name' => '年龄'
            ],
            [
                'field' => 'intro',
                'type' => 'textarea',
                'name' => '简介',
                'must' => 1
            ],
            [
                'field' => 'cover_img',
                'type' => 'img',
                'name' => '封面图片',
//                'value' => $show ? : ''
//                'verify' => 'img'
            ],
            [
                'field' => 'url',
                'type' => 'video',
                'name' => '视频内容',
                'sync' => $show ? $show->sync : 0,
//                'value' => $show ? \App\Jobs\VideoSlice::get_slice_url($show->url,'dash',$show->sync) :''
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '是否上架',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],
            [
                'field' => 'sync',
                'type' => 'radio',
                'name' => '启用专线',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],

        ];
        //赋值给UI数组里面,必须是form为key
        $this->uiBlade['form'] = $data;

    }

    //表单验证
    public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:video,name',
//            'cover_img'=>'required',
//            'cid'=>'required',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:video,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name'=>'直播名',
//            'cover_img'=>'封面图片',
//            'cid'=>'分类',
        ];
    }

    /*public function setModelRelaction($model)
    {
        return $model->with('category');
    }*/

    public function setListOutputItemExtend($item)
    {
        //$item->category_name = $item->category['name'] ?? '';
        $item->category_name = $this->getCatName($item->cat);
        $item->tag_name = $this->getTagName($item->tag);
        $item->status = UiService::switchTpl('status', $item,'','上架|下架');
        $item->is_recommend = UiService::switchTpl('is_recommend', $item,'','是|否');
        $item->sync = UiService::switchTpl('sync', $item,'','是|否');
        $item->type = UiService::switchTpl('type', $item,'','长|短');
        $item->restricted = $this->restrictedType[$item->restricted]['name'];
        $item->gold = $item->gold/$this->goldUnit;
        return $item;
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        if( isset($_REQUEST['callback_upload']) && ($_REQUEST['callback_upload']==1)){

            /*try {*/
                $job = new ProcessLive($model);
                // $this->dispatch($job);
                app(Dispatcher::class)->dispatchNow($job);
            /*}catch (\Exception $e){
                Log::error($e->getMessage());
            }*/
        }
        //ProcessSyncMiddleTable::dispatchAfterResponse('video');
        return $model;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $cats = $this->rq->input('cats',[]);
        $model->cat = json_encode($cats);
        $tags = $this->rq->input('tags',[]);
        $model->tag = json_encode($tags);
        $model->author = admin('nickname');
        $model->gold = $this->rq->input('gold',0);
        $model->gold *= $this->goldUnit;
        if(isset($model->url)){
            $model->dash_url = self::get_slice_url($model->url);
            $model->hls_url = self::get_slice_url($model->url,'hls');
            if(isset($model->cover_img) && !$model->cover_img){
                $model->cover_img = self::get_slice_url($model->url,'cover');
            }
        }
        //自动打标签
        if(isset($model->tagNames) && (empty($tags))){
            $tagLists = $this->getTagData();
            $tagArr = [];
            foreach ($tagLists as $tagList){
                $pos = strpos($model->tagNames,$tagList['name']);
                if($pos!==false){
                    $tagArr[] = $tagList['id'];
                }
            }
            if(!empty($tagArr)){
                $model->tag = json_encode($tagArr);
            }
        }

    }

    /*public function afterEditTableSuccessEvent($field, array $ids)
    {
        if($field==='sync'){
            foreach ($ids as $id){
                $row = AdminVideo::query()->find($id,['id','sync','url']);
                if($row->sync==1){
                    $job = new ProcessSyncVideo($row);
                    $this->dispatch($job);
                }
            }
        }
    }*/
    /*public function setOutputHandleBtnTpl($shareData)
    {
        $data = $this->defaultHandleBtnAddTpl($shareData);
        $data[] = [
            'name' => '同步',
            'id' => 'btn-sync',
        ];
        //赋值到ui数组里面必须是`btn`的key值
        $this->uiBlade['btn'] = $data;
    }*/

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '95%'; // TODO: Change the autogenerated stub
    }



    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '编号',
            ],
            [
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '直播名',
            ],
            [
                'field' => 'query_status',
                'type' => 'select',
                'name' => '是否上架',
                'default' => '',
                'data' => $this->uiService->trueFalseData(1)
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = $this->defaultHandleBtnAddTpl($shareData);
        if($this->isCanDel()){
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '修正时长',
                'id' => 'btn-autoUpDuration',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "duration_seconds",
                    'data-value' => 0,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '同步封面',
                'id' => 'btn-syncCoverImg',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "cover_img",
                    'data-value' => 0,
                ]
            ];
        }
        if ($this->isCanEdit()) {
            $data[] = [
                'class' => 'layui-btn-success',
                'name' => '批量上架',
                'id' => 'btn-putOnTheShelf',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量上架吗",
                    'data-field' => "status",
                    'data-value' => 1,
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-success',
                'name' => '批量下架',
                'id' => 'btn-downOnTheShelf',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量下架吗",
                    'data-field' => "status",
                    'data-value' => 0,
                ]
            ];
        }
        $this->uiBlade['btn'] = $data;
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
        //金币处理
        if($field == 'gold'){
            $value *= $this->goldUnit;
        }

        $id = 'id'; // 表主键id值

        $type_r = $this->editTableTypeEvent($id_arr, $field, $value);

        if ($type_r) {
            return $type_r;
        } else {
            switch ($field){
                case 'cover_img':
                    $covers = Live::query()->whereIn($id, $id_arr)->get(['id','cover_img']);
                    foreach ($covers as $cover){
                        $this->syncUpload($cover->cover_img);
                    }
                    $r=true;
                    break;
                case 'duration_seconds':
                    $videos = Video::query()->whereIn($id, $id_arr)->get(['id','duration','duration_seconds'])->toArray();
                    foreach ($videos as $video){
                        if(!empty($video['duration'])){
                            if($video['duration_seconds']==0){
                                $duration_seconds = $this->transferSeconds($video['duration']);
                                Video::query()->where('id',$video['id'])->update(['duration_seconds' => $duration_seconds]);
                            }
                        }else{
                            if(!empty($video['duration_seconds'])){
                                $format = $this->formatSeconds($video['duration_seconds']);
                                Video::query()->where('id',$video['id'])->update(['duration' => $format]);
                            }
                        }
                    }
                    $r = true;
                    break;
                default:
                    $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
                    break;
            }
            // 记录日志
            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }
    }
}
