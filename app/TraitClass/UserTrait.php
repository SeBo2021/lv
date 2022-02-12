<?php

namespace App\TraitClass;

use App\Models\User;

trait UserTrait
{
    public function getAreaNameFromUser($area=[])
    {
        $tmpArea = @json_decode($area ?? '', true);
        $tmpArea = $tmpArea ?? [];
        $locationName = '未知';
        if(!empty($tmpArea)){
            $locationName = $tmpArea[2] ?: ($tmpArea[1] ?: ($tmpArea[0]));
        }
        return $locationName;
    }
}