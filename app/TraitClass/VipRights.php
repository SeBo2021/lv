<?php

namespace App\TraitClass;

trait VipRights
{
    //收藏权限 todo
    public function collectRight($user): bool
    {
        $card = explode(',', ((string)$user->member_card_type ?? []));
        return !empty(array_intersect([3,4,5,6,7,8],$card));
    }

    //评论权限 todo
    public function commentRight($user): bool
    {
        $card = explode(',', ((string)$user->member_card_type ?? []));
        return !empty(array_intersect([3,4,5,6,7,8],$card));
    }

}