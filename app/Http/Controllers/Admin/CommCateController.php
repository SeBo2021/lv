<?php

namespace App\Http\Controllers\Admin;

use App\Models\CommCate;
use App\Services\UiService;
use App\TraitClass\PHPRedisTrait;

class CommCateController extends BaseCurlController
{

    public $pageName = '版块分类';

    use PHPRedisTrait;

    public function setModel(): CommCate
    {
        return $this->model = new CommCate();
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
    }

    //表单验证
    public function checkRule($id = ''): array
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

    public function checkRuleFieldName($id = ''): array
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
        $raw = CommCate::query()->orderBy('order', 'desc')
            ->select('id','name','parent_id','mark','order','is_allow_post','can_select_city')
            ->get()->toArray();
        foreach ($raw as $k1 => $v1) {
            $this->redis()->hSet('common_cate_help', "c_{$v1['id']}", $v1['mark']);
            if ($v1['parent_id'] == 0) {
                $data[] = $v1;
            };
        }
        foreach ($raw as $k2 => $v2) {
            if ($v2['parent_id'] != 0) {
                $this->redis()->hSet('common_cate_help', "c_{$v2['id']}", $v2['mark']);
                foreach ($data as $k3=>$v3) {
                    if ($v2['parent_id'] == $v3['id']) {
                        $data[$k3]['childs'][] = $v2;
                    }
                }
            };
        }
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


    /**
     * @param string $show
     * @return mixed
     */
    public function setOutputUiCreateEditForm($show = ''): void
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
                'data' => array_merge($this->uiService->allDataArr('请选择一级分类'), $this->uiService->treeData(CommCate::query()->where('parent_id', 0)->get()->toArray(), 0))//树形select

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
        $item->parent_name = $item->up['name'] ?? '';
        $item->is_allow_post_name = UiService::switchTpl('is_allow_post', $item,0,"是|否");
        return $item;
    }
}
