<?php
namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessLive;
use App\Models\Live;
use App\Services\UiService;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiveController extends BaseCurlController
{
    // 使用了video同样的切片原理
    use VideoTrait,PHPRedisTrait;

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
                'minWidth' => 20,
                'title' => '主播名',
                'align' => 'center',
            ],
            [
                'field' => 'age',
                'minWidth' => 10,
                'title' => '年龄',
                'align' => 'center',
            ],
            [
                'field' => 'intro',
                'minWidth' => 80,
                'title' => '简介',
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
                'tips' => '类似于视频名字,方便管理,(1到20个字符之内)',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'author',
                'type' => 'text',
                'tips' => '给主播一个性感的名字,(1到20个字符以内)',
                'name' => '主播名'
            ],
            [
                'field' => 'age',
                'type' => 'number',
                'tips' => '都喜欢18岁的,(正整数)',
                'name' => '年龄'
            ],
            [
                'field' => 'intro',
                'type' => 'textarea',
                'name' => '简介',
                'tips' => '比如有无在会所工作的经历,(1到50字符)',
                'must' => 1
            ],
            [
                'field' => 'cover_img',
                'type' => 'img',
                'name' => '封面图片',
                'value' => ($show && ($show->cover_img)) ? $this->getOriginEncImg($show->cover_img) : ''
            ],
            [
                'field' => 'url',
                'type' => 'video',
                'name' => '视频内容',
                'sync' => $show ? $show->sync : 0,
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
        $this->uiBlade['form'] = $data;

    }

    //表单验证
    public function checkRule($id = '')
    {
        $data = [
            'name'=>'required|unique:live,name',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:live,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name'=>'直播名',
        ];
    }

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,'','上架|下架');
        $item->sync = UiService::switchTpl('sync', $item,'','是|否');
        return $item;
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $ids = Live::where('status',1)->pluck('id')->toArray();
        $this->redis()->set('fakeLiveIds',implode(',',$ids));
        if( isset($_REQUEST['callback_upload']) && ($_REQUEST['callback_upload']==1)){
            try {
                $job = new ProcessLive($model);
                $this->dispatch($job->onQueue('high'));
                // app(Dispatcher::class)->dispatchNow($job);
            }catch (\Exception $e){
                Log::error($e->getMessage());
            }
        }
        //自定义上传封面
        if($model->cover_img){
            $this->syncUpload($model->cover_img);
        }
        return $model;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        // 预留分类与标签与金币
        $model->cat = json_encode([]);
        $model->tag = json_encode([]);
        $model->gold = 0;
        if(isset($model->url)){
            $model->dash_url = self::get_slice_url($model->url);
            $model->hls_url = self::get_slice_url($model->url,'hls');
            if(isset($model->cover_img) && !$model->cover_img){
                $model->cover_img = self::get_slice_url($model->url,'cover');
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

    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'by_id',
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
                    $lives = Live::query()->whereIn($id, $id_arr)->get(['id','duration','duration_seconds'])->toArray();
                    foreach ($lives as $v){
                        if(!empty($v['duration'])){
                            if($v['duration_seconds']==0){
                                $duration_seconds = $this->transferSeconds($v['duration']);
                                Live::query()->where('id',$v['id'])->update(['duration_seconds' => $duration_seconds]);
                            }
                        }else{
                            if(!empty($v['duration_seconds'])){
                                $format = $this->formatSeconds($v['duration_seconds']);
                                Live::query()->where('id',$v['id'])->update(['duration' => $format]);
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
