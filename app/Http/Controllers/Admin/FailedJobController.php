<?php

namespace App\Http\Controllers\Admin;

use App\Models\FailedJob;

class FailedJobController extends BaseCurlController
{
    //设置页面的名称
    public $pageName = '失败队列';

    //1.设置模型
    public function setModel(): FailedJob
    {
        return $this->model = new FailedJob();
    }

    public function setOutputHandleBtnTpl($shareData)
    {
        $this->uiBlade['btn'] = [];
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
                'field' => 'connection',
                'width' => 100,
                'title' => '连接驱动',
                'align' => 'center'
            ],
            [
                'field' => 'queue',
                'width' => 100,
                'title' => '队列',
                'align' => 'center'
            ],
            [
                'field' => 'payload',
                'minWidth' => 150,
                'title' => 'payload',
                'align' => 'center',
            ],
            [
                'field' => 'exception',
                'minWidth' => 200,
                'title' => '异常信息',
                'align' => 'center',
            ],
            [
                'field' => 'failed_at',
                'minWidth' => 170,
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
        if ($show && ($show->video??false) && $show->video != '[]') {
            $show->url = json_decode($show->video)[0];
        }
        $data = [
            [
                'field' => 'queue',
                'type' => 'text',
                'name' => '队列',
                'verify' => 'rq',
                'must' => 1
            ],
            [
                'field' => 'payload',
                'type' => 'textarea',
                'name' => '有效载荷',
                'verify' => 'rq',
                'must' => 1
            ],
            [
                'field' => 'exception',
                'type' => 'textarea',
                'name' => '异常信息',
                'attr' => 'rows=18',
                'verify' => 'rq',
                'must' => 1
            ],
            [
                'field' => 'failed_at',
                'type' => 'text',
                'default' => '',
                'name' => '时间',
                'must' => 0,
            ],

        ];
        //赋值到ui数组里面必须是`form`的key值
        $this->uiBlade['form'] = $data;
    }

    //弹窗大小
    public function layuiOpenWidth(): string
    {
        return '75%';
    }

    public function layuiOpenHeight(): string
    {
        return '95%';
    }
}