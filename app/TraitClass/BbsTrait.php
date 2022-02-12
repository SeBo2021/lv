<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;
use Illuminate\Support\Facades\Log;

trait BbsTrait
{
    use UserTrait;
    /**
     * @param $uid
     * @param $list
     * @param $user
     * @return array
     */
    private function proProcessData($uid, $list,$user=null): array
    {
        //Log::info('==userLocationName==',[$user]);
        foreach ($list as $k => $re) {
            if ($this->redis()->get("focus_{$uid}_{$re['uid']}") == 1) {
                $list[$k]['is_focus'] = 1;
            } else {
                $list[$k]['is_focus'] = 0;
            }
            if (!$re['video_picture']) {
                $list[$k]['video_picture'] = [];
            } else {
                $list[$k]['video_picture']  = [env('RESOURCE_DOMAIN') . (json_decode($re['video_picture'],true)[0]??'')];
            }
            if ($this->redis()->get("comm_like_{$uid}_{$re['id']}") == 1) {
                $list[$k]['is_love'] = 1;
            } else {
                $list[$k]['is_love'] = 0;
            }
            if ($re['location_name']) {
                $locationRaw = json_decode($re['location_name'],true);
                $list[$k]['location_name'] = $locationRaw[1]??$locationRaw[0]??'';
            }
            /*if($user!==null){
                $list[$k]['location_name'] = $this->getAreaNameFromUser($user->location_name);
            }*/
            $thumbsRaw = json_decode($re['thumbs'],true);
            $thumbs = [];
            foreach ($thumbsRaw as $itemP) {
                $thumbs[] = env('RESOURCE_DOMAIN') .$itemP;
            }
            $list[$k]['thumbs']  = $thumbs;

            $videoRaw  = json_decode($re['video'],true);
            $video = [];
            foreach ($videoRaw as $itemV) {
                $video[] = env('RESOURCE_DOMAIN') .$itemV;
            }
            $list[$k]['video']  = $video;
        }
        return $list;
    }
}
