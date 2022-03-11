<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;
use Illuminate\Support\Facades\Log;

trait BbsTrait
{
    use UserTrait,AboutEncryptTrait;
    /**
     * @param $uid
     * @param $list
     * @param $user
     * @return mixed
     */
    private function proProcessData($uid, $list, $user=null)
    {
        //Log::info('==userLocationName==',[$user]);
        $_v = date('Ymd');
        foreach ($list as $k => $re) {
            $domainSync = VideoTrait::getDomain($re['sync']);
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
                $thumbs[] = VideoTrait::getDomain($re['sync']) .$this->transferImgOut($itemP,$domainSync,$_v,'auto');
            }
            $list[$k]['thumbs']  = $thumbs;

            $videoRaw  = json_decode($re['video'],true);
            $video = [];
            foreach ($videoRaw as $itemV) {
                $video[] = VideoTrait::getDomain($re['sync']) .$itemV;
            }
            $list[$k]['video']  = $video;
        }
        return $list;
    }
}
