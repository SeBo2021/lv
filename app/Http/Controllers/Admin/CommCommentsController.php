<?php
// +----------------------------------------------------------------------
// | KQAdmin [ 基于Laravel后台快速开发后台 ]
// | 快速laravel后台管理系统，集成了，图片上传，多图上传，批量Excel导入，批量插入，修改，添加，搜索，权限管理RBAC,验证码，助你开发快人一步。
// +----------------------------------------------------------------------
// | Copyright (c) 2012~2019 www.haoxuekeji.cn All rights reserved.
// +----------------------------------------------------------------------
// | Laravel 原创视频教程，文档教程请关注 www.heibaiketang.com
// +----------------------------------------------------------------------
// | Author: kongqi <531833998@qq.com>`
// +----------------------------------------------------------------------

namespace App\Http\Controllers\Admin;

use App\Models\Article;
use App\Models\Category;
use App\Models\CommComments;
use App\Services\UiService;

class CommCommentsController extends BaseCurlController
{
    //那些页面不共享，需要单独设置的方法
    //public $denyCommonBladePathActionName = ['create'];
    //设置页面的名称
    public $pageName = '评论记录';

    //1.设置模型
    public function setModel()
    {
        return $this->model = new CommComments();
    }

    //2.首页的数据表格数组
    public function indexCols()
    {
        //这里99%跟layui的表格设置参数一样
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
                'field' => 'user_name',
                'minWidth' => 150,
                'title' => '评论者',
                'align' => 'center',

            ],
            [
                'field' => 'user_id',
                'minWidth' => 100,
                'title' => '用户ID',
                'align' => 'center',

            ],
            [
                'field' => 'bbs_content',
                'minWidth' => 120,
                'title' => '相关文章',
                'align' => 'center'
            ],
            [
                'field' => 'content',
                'minWidth' => 120,
                'title' => '内容',
                'align' => 'center'
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '审核',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '评论时间',
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
        $item->user_name = $item->user['nickname'] ?? '';
        $item->bbs_content = mb_substr($item->bbs['content'] ?? '',0,50);
        $item->content = mb_substr($item['content'] ?? '',0,50);
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