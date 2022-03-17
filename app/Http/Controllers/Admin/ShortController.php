<?php
namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessShort;
use App\Jobs\ProcessSyncMiddleTagTable;
use App\Jobs\ProcessVideoShort;
use App\Jobs\ProcessVideoShortMod;
use App\Models\AdminVideoShort;
use App\Models\Category;
use App\Models\Video;
use App\Models\VideoShort;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TagTrait;
use App\TraitClass\VideoShortTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShortController extends BaseCurlController
{
    use VideoTrait,CatTrait,TagTrait,GoldTrait,PHPRedisTrait,VideoShortTrait;

    public $pageName = '小视频管理';

    public array $cateAlias = [
        'short_hot',
        'limit_free',
        'short_rec'
    ];

    public function setModel()
    {
        return $this->model = new AdminVideoShort();
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
                'field' => 'category_name',
                'width' => 150,
                'title' => '版块',
                'align' => 'center',
            ],
            [
                'field' => 'tag_name',
                'minWidth' => 100,
                'title' => '标签',
                'align' => 'center',
            ],
            [
                'field' => 'name',
                'minWidth' => 150,
                'title' => '片名',
                'align' => 'center',
            ],
            [
                'field' => 'sync',
                'minWidth' => 80,
                'title' => '线路',
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
                'field' => 'url',
                'minWidth' => 150,
                'title' => '源视频',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'cat',
                'minWidth' => 100,
                'title' => '版块类别(JSON)',
                'align' => 'center',
                'hide' => true
            ],
            [
                'field' => 'tag',
                'minWidth' => 100,
                'title' => '标签(JSON)',
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
                'field' => 'hls_url',
                'minWidth' => 80,
                'title' => 'hls地址',
                'align' => 'center',
                'hide' => false
            ],
            /*[
                'field' => 'dash_url',
                'minWidth' => 80,
                'title' => 'dash地址',
                'align' => 'center',
                'hide' => false
            ],*/
            [
                'field' => 'cover_img',
                'minWidth' => 80,
                'title' => '封面图',
                'align' => 'center',
                'hide' => false
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
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $tag = $this->getTagData(2);
        $cats = $this->getCats(10000);
        //Log::info('==ShortCats===',[$cats]);
        $data = [
            [
                'field' => 'cats',
                'type' => 'checkbox',
                'name' => '版块',
                'verify' => '',
                'value' => ($show && ($show->cat)) ? json_decode($show->cat,true) : [],
                'data' => $cats
            ],
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '片名',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'tags',
                'type' => 'checkbox',
                'name' => '标签',
                'verify' => '',
                'value' => ($show && ($show->tag)) ? json_decode($show->tag,true) : [],
                'data' => $tag
            ],
            [
                'field' => 'url',
                'type' => 'video',
                'name' => '视频',
                'sync' =>  $show ? $show->sync : 0,
                'url' => $show ? $show->url : '',
                // 'value' => $show ? \App\Jobs\VideoSlice::getOrigin($show->sync,$show->url) :''
                'value' => $show ? $show->url :''
            ],
            [
                'field' => 'restricted',
                'type' => 'radio',
                'name' => '观看限制',
                'must' => 0,
                'default' => 1,
                'verify' => 'rq',
                'data' => [
                    0 => [
                        'id' => 0,
                        'name' => '免费'
                    ],
                    1 => [
                        'id' => 1,
                        'name' => 'VIP会员卡'
                    ],
                ]
            ],
            [
                'field' => 'status',
                'type' => 'radio',
                'name' => '是否上架',
                'verify' => '',
                'default' => 0,
                'data' => $this->uiService->trueFalseData()
            ],
            /*[
                'field' => 'sync',
                'type' => 'radio',
                'name' => '启用专线',
                'verify' => '',
                'default' => 1,
                'data' => $this->uiService->trueFalseData()
            ],*/

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
            'name'=>'片名',
//            'cover_img'=>'封面图片',
//            'cid'=>'分类',
        ];
    }

    public function setListOutputItemExtend($item)
    {
        $item->category_name = $this->getCatName($item->cat,10000);
        $item->tag_name = $this->getTagName($item->tag,2);
        $item->status = UiService::switchTpl('status', $item,'','上架|下架');
        $item->is_recommend = UiService::switchTpl('is_recommend', $item,'','是|否');
        //$item->sync = UiService::switchTpl('sync', $item,'','是|否');
        $item->type = UiService::switchTpl('type', $item,'','长|短');
        $item->restricted = $this->restrictedType[$item->restricted]['name'];
        $item->gold = $item->gold/$this->goldUnit;
        return $item;
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        // 更新redis
        /*$mapNum = $model->id % 300;
        $cacheKey = "short_video_$mapNum";
        $this->redis()->hSet($cacheKey, $model->id, json_encode([
            "id" => $model->id,
            "name" => $model->name,
            "cid" => $model->cid,
            "cat" => $model->cat,
            "tag" => $model->tag,
            "restricted" => $model->restricted,
            "sync" => $model->sync,
            "title" => $model->title,
            "url" => $model->url,
            "dash_url" => $model->dash_url,
            "hls_url" => $model->hls_url,
            "gold" => $model->gold,
            "duration" => $model->duration,
            "type" => $model->type,
            "views" => $model->views,
            "likes" => $model->likes,
            "comments" => $model->comments,
            "cover_img" => $model->cover_img,
            "updated_at" => $model->updated_at,
        ]));
        $ids = AdminVideoShort::where('status',1)->pluck('id')->toArray();
        $this->redis()->set('shortVideoIds',implode(',',$ids));*/
        $isVideo = ($_REQUEST['callback_upload']??0);
        if($isVideo>0){
            try {
//            $job = new ProcessShort($model,$isVideo);
                $job = new ProcessVideoShort($model);
                $this->dispatch($job->onQueue('high'));
                // app(Dispatcher::class)->dispatchNow($job);
            }catch (\Exception $e){
                Log::error($e->getMessage());
            }
        }else{
            $this->resetRedisVideoShort($model);
        }
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

    //弹窗大小
    public function layuiOpenWidth()
    {
        return '75%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '95%'; // TODO: Change the autogenerated stub
    }

    public function handleResultModel($model)
    {
        $cid = $this->rq->input('cid');
        $cat = $this->rq->input('cat');
        $tag = $this->rq->input('tag');
        $page = $this->rq->input('page', 1);
        $pagesize = $this->rq->input('limit', 30);
        $order_by_name = $this->orderByName();
        $order_by_type = $this->orderByType();
        $model = $this->orderBy($model, $order_by_name, $order_by_type);

        if(!$tag && !$cat && !$cid){
            $total = $model->count();
            $currentPageData = $model->forPage($page, $pagesize)->get();
        }else{
            $items = $model->get();
            $resultTag = $this->getSearchCheckboxResult($items,$tag,'tag');
            if($cid>0){
                $cat = Category::query()->where('parent_id',$cid)->get('id')->pluck('id')->all();
                $result = $this->getSearchCheckboxResult($resultTag,$cat,'cat');
            }else{
                if(!empty($resultTag)){
                    $result = $this->getSearchCheckboxResult($resultTag,$cat,'cat');
                }else{
                    $result = $this->getSearchCheckboxResult($items,$cat,'cat');
                }
            }
            $result = (array)$result ?? [];
            $total = count($result);
            //获取当前页数据
            $offset = ($page-1)*$pagesize;
            $currentPageData = array_slice($result,$offset,$pagesize);
        }

        return [
            'total' => $total,
            'result' => $currentPageData
        ];

    }

    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'cat',
                'type' => 'checkbox',
                'name' => '版块',
                'default' => [],
                'data' => array_merge($this->getCats(10000),[[
                    'id' => 0,
                    'name' => '无'
                ]])
            ],
            [
                'field' => 'tag',
                'type' => 'checkbox',
                'name' => '标签',
                'default' => [],
                'data' => array_merge($this->getTagData(2),[[
                    'id' => 0,
                    'name' => '无'
                ]])
            ],
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '编号',
            ],
            [
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '片名',
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
                'name' => '远程切片',
                'id' => 'btn-originSlice',
                'data'=>[
                    'data-type' => "handle",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "slice",
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

            $data[] = [
                'class' => 'layui-btn-dark',
                'name' => '批量版块',
                'id' => 'btn-batchCat',
                'data'=>[
                    'data-type' => "batchHandle",
                    'data-input-type' => "checkbox",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "cat",
                ]
            ];
            $data[] = [
                'class' => 'layui-btn-dark',
                'name' => '批量标签',
                'id' => 'btn-batchTag',
                'data'=>[
                    'data-type' => "batchHandle",
                    'data-input-type' => "checkbox",
                    'data-title' => "确定批量操作吗",
                    'data-field' => "tag",
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
                case 'slice':
                    $items = VideoShort::query()->whereIn($id, $id_arr)->get(['id','url','hls_url','dash_url']);
                    //$domain = env('RESOURCE_DOMAIN');
                    foreach ($items as $item){
                        $job = new ProcessVideoShortMod($item);
                        $this->dispatch($job);
                    }
                    $r=true;
                    break;
                case 'cover_img':
                    $covers = VideoShort::query()->whereIn($id, $id_arr)->get(['id','cover_img']);
                    foreach ($covers as $cover){
                        $this->syncUpload($cover->cover_img);
                    }
                    $r=true;
                    break;
                case 'cat':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = VideoShort::query()->whereIn($id, $id_arr);
                    $buildQueryVideo->update(['cat'=>json_encode($value_arr)]);
                    $r=true;
                    break;
                case 'tag':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = VideoShort::query()->whereIn($id, $id_arr);
                    $buildQueryVideo->update(['tag'=>json_encode($value_arr)]);
                    $r=true;
                    break;
                case 'duration_seconds':
                    $videos = VideoShort::query()->whereIn($id, $id_arr)->get(['id','duration','duration_seconds'])->toArray();
                    foreach ($videos as $video){
                        if(!empty($video['duration'])){
                            if($video['duration_seconds']==0){
                                $duration_seconds = $this->transferSeconds($video['duration']);
                                VideoShort::query()->where('id',$video['id'])->update(['duration_seconds' => $duration_seconds]);
                            }
                        }else{
                            if(!empty($video['duration_seconds'])){
                                $format = $this->formatSeconds($video['duration_seconds']);
                                VideoShort::query()->where('id',$video['id'])->update(['duration' => $format]);
                            }
                        }
                    }
                    $r = true;
                    break;
                default:
                    $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
                    break;
            }

            if ($r) {
                $this->insertLog($this->getPageName() . lang('短视频-成功修改ids') . '：' . implode(',', $id_arr));
                //清除缓存
                $this->redisBatchDel($this->redis()->keys($this->apiRedisKey['home_lists'] . '*'));
            } else {
                $this->insertLog($this->getPageName() . lang('短视频-失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }

    }

}
