<?php


namespace App\Http\Controllers\Admin;


use App\Models\Carousel;
use App\Models\Category;
use App\Services\UiService;
use App\TraitClass\AboutEncryptTrait;
use App\TraitClass\CatTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;

class CarouselController extends BaseCurlController
{
    use CatTrait, AboutEncryptTrait, PHPRedisTrait,VideoTrait;

    public $pageName = '轮播图管理';

    public function setModel(): Carousel
    {
        return $this->model = new Carousel();
    }

    public function getCateGoryData(): array
    {
        return array_merge($this->uiService->allDataArr('请选择分类'), $this->uiService->treeData(Category::checked()->get()->toArray(), 0));//树形select
    }

    public function indexCols(): array
    {
        //要返回给数组
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
                'title' => '分类',
                'align' => 'center',
//                'edit' => 1
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
                'title' => '链接地址',
                'align' => 'center',

            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '状态',
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
                'field' => 'cid',
                'type' => 'select',
                'name' => '首页二级分类',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => $this->getCatNavData()
            ],
            [
                'field' => 'title',
                'type' => 'text',
                'name' => '标题',
                'must' => 0,
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
                'field' => 'action_type',
                'type' => 'text',
                'name' => '操作',
                'must' => 0,
                'tips' => '0-无操作,1-打开链接',
                'default' => '',
            ],
            [
                'field' => 'url',
                'type' => 'text',
                'name' => '链接地址',
                'must' => 0,
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
                //'default' => $show->start_at.'-'.$show->end_at,
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
                'field' => 'sort',
                'type' => 'text',
                'name' => '排序',
                'must' => 0,
                'default' => '',
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
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function afterSaveSuccessEvent($model, $id = '')
    {
        $coverImg = str_replace(self::getDomain(env('SFTP_SYNC',1)),"",$model->img);
        $model->img = $coverImg;
        $model->save();
        $this->syncUpload($model->img);

        /*$api_carousel_keys = $this->redis()->keys('api_carousel_*');
        $this->redisBatchDel($api_carousel_keys);*/
        $cats = Category::query()
            ->where('is_checked',1)
            ->where('parent_id',2)
            ->orderBy('sort')
            ->get(['id']);
        foreach ($cats as $cat){
            $carousel = Carousel::query()
                ->where('status', 1)
                ->where('cid', $cat->id)
                ->get(['id','title','img','url','action_type','vid','status','end_at'])->toArray();
            $this->redis()->set('api_carousel_'.$cat->id,json_encode($carousel,JSON_UNESCAPED_UNICODE));
        }
    }

    public function setListOutputItemExtend($item)
    {
        $item->category_name = $item->category['name'] ?? '';
        $item->status = ($item->status==1 || ($item->end_at && (strtotime($item->end_at)>time()))) ? '启用' : '关闭';
        return $item;
    }

    //表单验证
    public function checkRule($id = '')
    {
        return [
//            'title'=>'required',
            'img'=>'required',
//            'url'=>'required',
        ];
    }

    public function checkRuleFieldName($id = '')
    {
        return [
//            'title'=>'标题',
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

        $api_carousel_keys = $this->redis()->keys('api_carousel_*');

        if ($type_r) {
            $this->redisBatchDel($api_carousel_keys);
            return $type_r;
        } else {
            $r = $this->editTableAddWhere()->whereIn($id, $id_arr)->update([$field => $value]);
            $this->redisBatchDel($api_carousel_keys);
            if ($r) {
                $this->insertLog($this->getPageName() . lang('成功修改ids') . '：' . implode(',', $id_arr));
            } else {
                $this->insertLog($this->getPageName() . lang('失败ids') . '：' . implode(',', $id_arr));
            }
            return $this->editTablePutLog($r, $field, $id_arr);
        }
    }

}
