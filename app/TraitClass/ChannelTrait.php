<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;

trait ChannelTrait
{
    public array $deviceSystems = [
        0 => 'default',
        1 => '苹果',
        2 => '安卓',
        3 => 'ios轻量版',
    ];

    public array $bindPhoneNumSelectData = [
        '' => [
            'id' =>'',
            'name' => '全部',
        ],
        0 => [
            'id' => 0,
            'name' => '未绑定',
        ],
        1 => [
            'id' => 1,
            'name' => '已绑定',
        ],
    ];

    public function getChannelSelectData($all=null): array
    {
        $queryBuild = DB::table('channels');
        if($all===null){
            $queryBuild = $queryBuild->where('status',1);
        }
        $items = [ ''=>'全部',0 => '官方'] + $queryBuild->pluck('name','id')->all();
        $lists = [];
        foreach ($items as $key => $value){
            $lists[$key] = [
                'id' => $key,
                'name' => $value,
            ];
        }
        return $lists;
    }

    //顶级渠道
    public function getTopChannels($type=null)
    {
        $buildChannel = DB::table('channels')->where('status',1);
        if($type!==null){
            $buildChannel = $buildChannel->where('type',$type);
        }
        $res = $buildChannel->where('pid',0)->get(['id','name']);
        $data = $this->uiService->allDataArr('请选择渠道(一级)');
        foreach ($res as $item) {
            $data[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
            ];
        }
        return $data;
    }

    public function getAllChannels($type=null): array
    {
        if($type!==null){
            $queryBuild = DB::table('channels')
                ->where('status',1)
                ->where('type',$type);
            $items = [ ''=>'全部'] + $queryBuild->pluck('name','id')->all();
            $lists = [];
            foreach ($items as $key => $value){
                $lists[$key] = [
                    'id' => $key,
                    'name' => $value,
                ];
            }
            return $lists;
        }
        return $this->getChannelSelectData();
    }
}