<?php

namespace App\Http\Controllers\Admin;

use App\Models\ShortComment;
use App\Services\UiService;

class ShortCommentController extends BaseCurlIndexController
{
    public $pageName = '评论';

    public function setModel()
    {
        return $this->model = new ShortComment();
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
                'field' => 'reply_cid',
                'minWidth' => 100,
                'title' => '回复编号',
                'sort' => 1,
                'align' => 'center'
            ],
            [
                'field' => 'vid',
                'minWidth' => 80,
                'title' => '视频ID',
                'sort' => 1,
                'align' => 'center',
                'edit' => 1
            ],
            [
                'field' => 'uid',
                'minWidth' => 80,
                'title' => '用户ID',
                'align' => 'center',
            ],
            [
                'field' => 'content',
                'minWidth' => 150,
                'title' => '评论内容',
                'align' => 'center',
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '审核',
                'align' => 'center',
            ],
            [
                'field' => 'reply_at',
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

        return $cols;
    }

    //3.设置搜索数据表单
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [

            [
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '评论者id',
            ],
            [
                'field' => 'query_category_id',
                'type' => 'text',
                'name' => '文章id',
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,0,"通过|待审核");
        $item->handle = UiService::editDelTpl(0,1);
        return $item;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = [];
        if ($this->isCanDel()) {
            $data[] = [
                'class' => 'layui-btn-danger',
                'name' => '删除',
                'data' => [
                    'data-type' => "allDel"
                ]
            ];
        }
        $this->uiBlade['btn'] = $data;
    }
}