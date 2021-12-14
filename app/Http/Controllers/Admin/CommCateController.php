<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessPreviewVideo;
use App\Jobs\ProcessSyncMiddleTable;
use App\Jobs\ProcessSyncMiddleSectionTable;
use App\Jobs\ProcessSyncMiddleTagTable;
use App\Jobs\ProcessVideoSlice;
use App\Jobs\VideoSlice;
use App\Models\AdminVideo;
use App\Models\Category;
use App\Models\CommChat;
use App\Models\CommCate;
use App\Models\Tag;
use App\Models\Video;
use App\Services\UiService;
use App\TraitClass\CatTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\TagTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommCateController extends BaseCurlController
{

    public $pageName = '版块分类';

    use PHPRedisTrait;

    public function setModel()
    {
        return $this->model = new CommCate();
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
                'width' => 150,
                'title' => '版块名',
                'align' => 'center',
            ],
            [
                'field' => 'parent_name',
                'width' => 150,
                'title' => '上级版块名',
                'align' => 'center',
            ],
            [
                'field' => 'order',
                'width' => 150,
                'title' => '排序',
                'align' => 'center',
            ],
            [
                'field' => 'mark',
                'minWidth' => 100,
                'title' => '标识',
                'align' => 'center',
            ],
            [
                'field' => 'is_allow_post_name',
                'minWidth' => 80,
                'title' => '是否允许发帖',
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

    //表单验证
    public function checkRule($id = '')
    {
        $data = [
            'name' => 'required|unique:community_cate,name',
        ];
        //$id值存在表示编辑的验证
        if ($id) {
            $data['name'] = 'required|unique:community_cate,name,' . $id;
        }
        return $data;
    }

    public function checkRuleFieldName($id = '')
    {
        return [
            'name' => '版块名',
        ];
    }

    protected function afterSaveSuccessEvent($model, $id = '')
    {
        $this->processCache();
        return $model;
    }

    private function processCache() {
        $data = [];
        $raw = CommCate::orderBy('order', 'desc')->select('id','name','parent_id','mark','is_allow_post','can_select_city')->get();
        array_map(function ($datum) use (&$data) {
            $this->redis()->hSet('common_cate_help', "c_{$datum['id']}", $datum['mark']);
            if ($datum['parent_id'] == 0) {
                $data[] = $datum;
            } else {
                foreach ($data as $k => $v) {
                    if ($v['id'] == $datum['parent_id']) {
                        $data[$k]['child'][] = $datum;
                    }
                }
            }
        }, $raw->toArray());
        $this->redis()->set('common_cate',json_encode($data));
    }
    /**
     * 成功删除之后要操作的事
     * @param $ids
     */
    public function deleteSuccessAfter(array $ids)
    {
        $this->processCache();
    }


    public function setOutputUiCreateEditForm($show = '')
    {
        $data = [
            [
                'field' => 'name',
                'type' => 'text',
                'name' => '版块名',
                'tips' => '版块名',
                'must' => 1,
                'verify' => 'rq',
            ],
            [
                'field' => 'parent_id',
                'type' => 'select',
                'name' => '一级分类',
                'default' => 0,
                'data' => array_merge($this->uiService->allDataArr('请选择一级分类'), $this->uiService->treeData(CommCate::where('parent_id', 0)->get()->toArray(), 0))//树形select

            ],
            [
                'field' => 'mark',
                'type' => 'text',
                'tips' => 'api接口中使用',
                'name' => '标识',
                'verify' => 'rq',
            ],
            [
                'field' => 'is_allow_post',
                'type' => 'radio',
                'name' => '是否允许发帖',
                'verify' => '',
                'default' => 1,
                'data' => [
                    1=>[
                        'id' => '1',
                        'name' => '是'
                    ],
                    2=>[
                        'id' => '0',
                        'name' => '否'
                    ]
                ],
            ],
            [
                'field' => 'order',
                'type' => 'number',
                'tips' => '值越大越靠前',
                'name' => '排序',
                'verify' => 'rq',
            ],
        ];
        $this->uiBlade['form'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        //$item->category_name = $item->category['name'] ?? '';
        $item->parent_name = $item->up['name'] ?? '';
        $item->is_allow_post_name = UiService::switchTpl('is_allow_post', $item,0,"是|否");
        return $item;
    }
}
