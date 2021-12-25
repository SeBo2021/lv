<?php
namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessShort;
use App\Jobs\ProcessSyncMiddleSectionTable;
use App\Jobs\ProcessSyncMiddleTagTable;
use App\Models\Category;
use App\Models\Video;
use App\Models\VideoShort;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TagTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;

class ShortController extends BaseCurlController
{
    use VideoTrait,CatTrait,TagTrait,GoldTrait,PHPRedisTrait;

    public $pageName = '小视频管理';


    public function setModel()
    {
        return $this->model = new VideoShort();
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
                'title' => '专线',
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
                'field' => 'tagNames',
                'minWidth' => 100,
                'title' => '自动标签内容',
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

    /*public function getCateGoryData()
    {
        return array_merge($this->uiService->allDataArr('请选择分类'), $this->uiService->treeData(Category::checked()->get()->toArray(), 0));//树形select
    }*/
    public function getCatNavData()
    {
        $res = Category::query()
            ->where('is_checked',1)
            ->where('parent_id',2)
            ->orderBy('sort')
            ->get(['id','name']);
        $data = $this->uiService->allDataArr('请选择分类');
        foreach ($res as $item) {
            $data[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
            ];
        }
        return $data;
    }

    public function setOutputUiCreateEditForm($show = '')
    {
        $tag = $this->getTagData(2);
        $cats = $this->getCats(10000);
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
                'field' => 'tagNames',
                'type' => 'text',
                'tips' => '输入包含标签词的内容即可,格式不限,如:#内射#口交#人妻...',
                'name' => '自动标签内容'
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
                'type' => 'movie',
                'name' => '视频',
                'sync' =>  $show ? $show->sync : 0,
                'url' => $show ? $show->url : '',
                // 'value' => $show ? \App\Jobs\VideoSlice::getOrigin($show->sync,$show->url) :''
                'value' => $show ? $show->url :''
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
        $item->sync = UiService::switchTpl('sync', $item,'','是|否');
        $item->type = UiService::switchTpl('type', $item,'','长|短');
        $item->restricted = $this->restrictedType[$item->restricted]['name'];
        $item->gold = $item->gold/$this->goldUnit;
        return $item;
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $isVideo = ($_REQUEST['callback_upload']??0);
        /*try {*/
        //$job = new VideoSlice($model);
        $job = new ProcessShort($model,$isVideo);
        // $this->dispatch($job);
        app(Dispatcher::class)->dispatchNow($job);
        /*}catch (\Exception $e){
            Log::error($e->getMessage());
        }*/
        //  }
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
            [
                'field' => 'cid',
                'type' => 'select',
                'name' => '分类',
                'default' => '',
                'data' => $this->getCatNavData()
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
                case 'cover_img':
                    $covers = Video::query()->whereIn($id, $id_arr)->get(['id','cover_img']);
                    foreach ($covers as $cover){
                        $this->syncUpload($cover->cover_img);
                    }
                    $r=true;
                    break;
                case 'cat':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = Video::query()->whereIn($id, $id_arr);
                    $buildQueryVideo->update(['cat'=>json_encode($value_arr)]);
                    //队列执行更新版块中间表
                    ProcessSyncMiddleSectionTable::dispatchAfterResponse();
                    $r=true;
                    break;
                case 'tag':
                    $value_arr = explode(',',$value);
                    $buildQueryVideo = Video::query()->whereIn($id, $id_arr);
                    $buildQueryVideo->update(['tag'=>json_encode($value_arr)]);
                    //更新标签中间表
                    ProcessSyncMiddleTagTable::dispatchAfterResponse();
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

            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
                //清除缓存
                $this->redisBatchDel($this->redis()->keys($this->apiRedisKey['home_lists'] . '*'));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }

    }

}
