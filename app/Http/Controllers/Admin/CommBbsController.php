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
use App\Models\Bbs;
use App\Models\Category;
use App\Models\CommBbs;
use App\Models\CommCate;
use App\Models\User;
use App\Services\UiService;

class CommBbsController extends BaseCurlController
{
    //那些页面不共享，需要单独设置的方法
    //public $denyCommonBladePathActionName = ['create'];
    //设置页面的名称
    public $pageName = '帖子记录';

    //1.设置模型
    public function setModel()
    {
        return $this->model = new CommBbs();
    }

    //2.首页的数据表格数组
    public function indexCols()
    {
        //这里99%跟layui的表格设置参数一样
        $data = [
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
                'minWidth' => 120,
                'title' => '版块名称',
                'align' => 'center'
            ],
            [
                'field' => 'content',
                'minWidth' => 120,
                'title' => '内容缩略',
                'align' => 'left'
            ],
            /*[
                'field' => 'thumbs',
                'minWidth' => 120,
                'title' => '附加图集',
                'align' => 'left'
            ],*/
            [
                'field' => 'likes',
                'minWidth' => 80,
                'title' => '点赞数',
                'align' => 'center',
            ],
            [
                'field' => 'comments',
                'minWidth' => 80,
                'title' => '评论数',
                'align' => 'center',
            ],
            [
                'field' => 'rewards',
                'minWidth' => 80,
                'title' => '打赏数',
                'align' => 'center',
            ],
            [
                'field' => 'created_at',
                'minWidth' => 150,
                'title' => '发布时间',
                'align' => 'center'
            ],
            [
                'field' => 'status',
                'minWidth' => 80,
                'title' => '审核',
                'align' => 'center',
            ],
            [
                'field' => 'handle',
                'minWidth' => 150,
                'title' => '操作',
                'align' => 'center'
            ]
        ];
        //要返回给数组
        return $data;
    }


    //3.设置搜索数据表单
    public function setOutputSearchFormTpl($shareData)
    {
        $data = [
            [
                'field' => 'id',
                'type' => 'text',
                'name' => '文章ID',
            ],
            [
                'field' => 'query_category_id',
                'type' => 'select',
                'name' => '版块',
                'default' => '1',
                'data' => array_merge([['id' => '', 'name' => '全部']], CommCate::get()->toArray())

            ]

        ];
        //赋值到ui数组里面必须是`search`的key值
        $this->uiBlade['search'] = $data;
    }

    //4.编辑和添加页面表单数据
    public function setOutputUiCreateEditForm($show = '')
    {
        if ($show->video != '[]') {
            $show->url = json_decode($show->video)[0];
        }
        $data = [
            [
                'field' => 'author_id',
                'type' => 'select',
                'name' => '关联官方用户',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => User::where('is_office',1)->select('id','nickname as name')->get()->toArray()

            ],
            [
                'field' => 'category_id',
                'type' => 'select',
                'name' => '版块',
                'must' => 1,
                'verify' => 'rq',
                'default' => 0,
                'data' => array_merge($this->uiService->allDataArr('请选择版块'), $this->uiService->treeData(CommCate::get()->toArray(), 0))//树形select
            ],
            [
                'field' => 'thumbs',
                'type' => 'imgMore',
                'default' => '',
                'name' => '相册',
                'must' => 0,
                'verify' => ''
            ],
            [
                'field' => 'video',
                'type' => 'movie',
                'name' => '视频',
                'sync' =>  0,
                'url' => $show ? $show->url : '',
                // 'value' => $show ? \App\Jobs\VideoSlice::getOrigin($show->sync,$show->url) :''
                'value' => $show ? $show->url :''
            ],
            [
                'field' => 'content',
                'type' => 'textarea',
                'name' => '内容',
                'verify' => 'rq',
                'must' => 1
            ],
        ];
        //赋值到ui数组里面必须是`form`的key值
        $this->uiBlade['form'] = $data;
    }

    //表单验证规则

    public function checkRule($id = '')
    {
        if ($id) {
            //$id值存在，表示编辑的规则，可以写你的验证规则，跟laravel写法一样，只是抽出来而已
        }
        return [

        ];
    }

    public function checkRuleFieldName()
    {
        return [
            'name' => '名称',
            'category_id' => '分类'
        ];
    }


    //弹窗大小
    public function layuiOpenWidth()
    {
        return '80%'; // TODO: Change the autogenerated stub
    }

    public function layuiOpenHeight()
    {
        return '80%'; // TODO: Change the autogenerated stub
    }

    public function setListOutputItemExtend($item)
    {
        $item->category_name = $item->category['name'] ?? '';
        $item->status = UiService::switchTpl('status', $item,0,"是|否");
        return $item;
    }

    public function beforeSaveEvent($model, $id = '')
    {
        $thumbs = $this->rq->input('thumbs','');
        if(!$thumbs){
            $model->thumbs = '[]';
        } else {
            $fixPic = [];
            $raw = json_decode($thumbs,true);
            foreach ($raw as $item) {
                $fixPic[] = $item['path'];
            }
            var_dump(json_encode($fixPic));
            $model->thumbs = json_encode($fixPic);
        }

        $video = $this->rq->input('video','');
        if(!$video){
            $model->video = '[]';
        }

        $videoPicture = $this->rq->input('video_picture','');
        if(!$videoPicture){
            $model->video_picture = '[]';
        }
    }
}