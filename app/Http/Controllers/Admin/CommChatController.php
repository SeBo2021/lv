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
use App\Models\CommChat;

class CommChatController extends BaseCurlController
{
    //那些页面不共享，需要单独设置的方法
    //public $denyCommonBladePathActionName = ['create'];
    //设置页面的名称
    public $pageName = '私聊记录';

    //1.设置模型
    public function setModel()
    {
        return $this->model = new CommChat();
    }

    //2.首页的数据表格数组
    public function indexCols()
    {
        //这里99%跟layui的表格设置参数一样
        $data = [
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
                'title' => '发送者',
                'align' => 'center',

            ],
            [
                'field' => 'to_user_name',
                'minWidth' => 120,
                'title' => '接收者',
                'align' => 'center'
            ],
            [
                'field' => 'content',
                'minWidth' => 120,
                'title' => '内容',
                'align' => 'center'
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '发送时间',
                'align' => 'center'
            ],
        ];
        //要返回给数组
        return $data;
    }

    //3.设置搜索数据表单
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [

            [
                'field' => 'query_like_name',//这个搜索写的查询条件在app/TraitClass/QueryWhereTrait.php 里面写
                'type' => 'text',
                'name' => '发送者id',
            ],
            [
                'field' => 'query_category_id',
                'type' => 'text',
                'name' => '接收者id',
            ],
        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    public function setListOutputItemExtend($item)
    {
        $item->user_name = $item->user['nickname'] ?? '';
        $item->to_user_name = $item->toUser['nickname'] ?? '';
        $item->content = mb_substr($item['content'] ?? '',0,50);
        return $item;
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        return [];
    }
}