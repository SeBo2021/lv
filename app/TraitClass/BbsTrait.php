<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;

trait BbsTrait
{
    /**
     * @param $uid
     * @param $list
     * @return mixed
     */
    private function proProcessData($uid, $list): mixed
    {
        foreach ($list as $k => $re) {
            if ($this->redis()->get("focus_{$uid}_{$re['uid']}") == 1) {
                $list[$k]['is_focus'] = 1;
            } else {
                $list[$k]['is_focus'] = 0;
            }
            if (!$re['video_picture']) {
                $list[$k]['video_picture'] = [];
            } else {
                $list[$k]['video_picture']  = json_decode($re['video_picture'],true);
            }
            if ($this->redis()->get("comm_like_{$uid}_{$re['id']}") == 1) {
                $list[$k]['is_love'] = 1;
            } else {
                $list[$k]['is_love'] = 0;
            }
            if (!$re['location_name']) {
                $locationRaw = json_decode($re['location_name'],true);
                $list[$k]['location_name'] = $locationRaw[1]??$locationRaw[0]??'';
            }
            $list[$k]['thumbs']  = json_decode($re['thumbs'],true);
            $list[$k]['video']  = json_decode($re['video'],true);
        }
        return $list;
    }
}
