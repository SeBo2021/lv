<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;

trait MemberCardTrait
{
    public array $show_user = [
        0 => [
            'id' => 0,
            'name' => '全部'
        ],
        1 => [
            'id' => 1,
            'name' => '新用户'
        ],
        2 => [
            'id' => 2,
            'name' => '老用户'
        ],
        3 => [
            'id' => 3,
            'name' => 'VIP用户低于7天的用户'
        ],
    ];

    public array $cardRights = [
        1 => [
            'id' => 1,
            'icon' => 1,
            'name' => '观看VIP影片'
        ],
        2 => [
            'id' => 2,
            'icon' => 2,
            'name' => '会员福利群'
        ],
        3 => [
            'id' => 3,
            'icon' => 3,
            'name' => '会员专有标识'
        ],
        4 => [
            'id' => 4,
            'icon' => 4,
            'name' => '空降女友抽奖'
        ],
        5 => [
            'id' => 5,
            'icon' => 5,
            'name' => '祼聊外围'
        ],
        6 => [
            'id' => 6,
            'icon' => 6,
            'name' => '评论特权'
        ],
        7 => [
            'id' => 7,
            'icon' => 7,
            'name' => '骚豆影片免费'
        ],
        8 => [
            'id' => 8,
            'icon' => 8,
            'name' => '收藏特权'
        ],
        9 => [
            'id' => 9,
            'icon' => 9,
            'name' => '专属客服'
        ],
        10 => [
            'id' => 10,
            'icon' => 10,
            'name' => '小视频特权'
        ],
        11 => [
            'id' => 11,
            'icon' => 11,
            'name' => '社交特权'
        ],
        12 => [
            'id' => 12,
            'icon' => 12,
            'name' => '视频直播'
        ],
    ];

    public function numToRights($num): array
    {
        $rights = [];
        foreach ($this->cardRights as $right)
        {
            $pos = $right['id']-1;
            if((($num >> $pos) & 1) == 1){
                $rights[] = $right['id'];
            }
        }
        return $rights;
    }

    public function getRightsName($num): string
    {
        $ids = $this->numToRights($num);
        $name = '';
        $char = '||';
        foreach ($ids as $id)
        {
            $name .= $this->cardRights[$id]['name'] . $char;
        }
        return rtrim($name,$char);
    }

    public function binTypeToNum($rights): float|object|int
    {
        $num = 0;
        foreach ($rights as $right)
        {
            $num += pow(2,$right-1);
        }
        return $num;
    }

    public function getMemberCardList($except=null): array
    {
        $queryBuild = DB::table('member_card');
        $items = match ($except) {
            'gold' => ['' => ''] + $queryBuild->pluck('name', 'id')->all(),
            'default' => $queryBuild->pluck('name', 'id')->all(),
            default => ['' => '全部', 0 => '金币'] + $queryBuild->pluck('name', 'id')->all(),
        };
        $lists = [];
        foreach ($items as $key => $value){
            $lists[$key] = [
                'id' => $key,
                'name' => $value,
            ];
        }
        return $lists;
    }

    public function isForeverCard($memberCardTypeId): bool
    {
        $hasMemberCards = DB::table('member_card')->get(['id','name','value','expired_hours']);
        $forever = false;
        foreach ($hasMemberCards as $memberCard){
            if($memberCard->id == $memberCardTypeId){
                if($memberCard->expired_hours == 0){ //永久卡
                    $forever = true;
                }
                break;
            }
        }
        return $forever;
    }

    public function isVip($user): bool
    {
        $isVip = true;
        $types = explode(',',$user->member_card_type);
        if(!empty($types)){
            $memberCardTypeId = $types[0];
            $forever = $this->isForeverCard($memberCardTypeId);
            if(!$forever){
                if(time() - $user->vip_expired > $user->vip_start_last){
                    $isVip = false;
                }
            }
        }else{
            $isVip = false;
        }
        return $isVip;
    }

}