<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Services\UiService;

class CommentController extends BaseCurlController
{
    public $pageName = '评论';

    public function setModel()
    {
        return $this->model = new Comment();
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

    public function setOutputHandleBtnTpl($shareData)
    {
        $data = [];
        /*if ($this->isCanCreate()) {

            $data[] = [
                'name' => '添加',
                'data' => [
                    'data-type' => "add"
                ]
            ];
        }*/
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

    public function setListOutputItemExtend($item)
    {
        $item->status = UiService::switchTpl('status', $item,0,"通过|待审核");
        $item->handle = UiService::editDelTpl(0,1);
        return $item;
    }

}