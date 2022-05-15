<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;
use Illuminate\Support\Facades\Cache;

trait AdTrait
{
    use AboutEncryptTrait,PHPRedisTrait;

    public function getConfigDataFromDb(): array
    {
        $appConfig = config_cache('app');
        if(!empty($appConfig)){
            //Log::info('==ConfigAnnouncement==',[$appConfig['announcement']]);
            $res['announcement'] = stripslashes(addslashes($appConfig['announcement']));
            $res['anActionType'] = $appConfig['announcement_action_type'];
            //视频ID
            $res['videoId'] = $appConfig['announcement_video_id'];
            $res['obUrl'] = $appConfig['announcement_url'];
            $res['adTime'] = (int)$appConfig['ad_time'];
            $res['version'] = $appConfig['app_version'];
            $res['kf_url'] = $appConfig['kf_url'];
            $res['send_sms_intervals'] = (int)$appConfig['send_sms_intervals'];
            //广告部分
            $ads = $this->weightGet('open_screen');
            $activityAds = $this->weightGet('activity','weight',true);
            $res['open_screen_ads'] = $ads;
            $res['activity_ads'] = $activityAds;

            $payConf = json_decode($appConfig['pay_method']??'',true);
            $currentSecond = strval(date(date('s')%10));
            $res['pay_method'] = intval($payConf[$currentSecond]??2);
            $res['pay_detail'] = json_decode($appConfig['pay_detail']??'',true);
            if(!empty($res)){
                $this->redis()->set('api_config',json_encode($res,JSON_UNESCAPED_UNICODE));
                return $res;
            }
        }
        return [];
    }

    public function weightGet($flag='',$sortFiled='sort',$more=false): array
    {
        $ads = Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->orderByDesc($sortFiled)
            ->get(['id','name','weight','title','img','position','url','play_url','type','status','action_type','vid','end_at'])
            ->toArray();
        $domain = VideoTrait::getDomain(env('SFTP_SYNC',1));
        $_v = date('Ymd');

        if($more){
            foreach ($ads as &$item){
                $item['img'] = $this->transferImgOut($item['img'],$domain,$_v,'auto');
                $item['action_type'] = (string) $item['action_type'];
                $item['vid'] = (string) $item['vid'];
            }
            return $ads;
        }

        $one = [];

        foreach ($ads as $ad){
            $weight = $ad['weight']; //权重值要设置在一到10的范围
            $randValue = rand(1,10);
            if($randValue <= $weight){
                $one = $ad;
                break;
            }
        }
        if(!empty($ads)){
            if(empty($one)){ //若未命中权重概率,则随机取一
                $key = array_rand($ads);
                $one = $ads[$key];
            }
            //图片处理
            $one['img'] = $this->transferImgOut($one['img'],$domain,$_v,'auto');
            $one['action_type'] = (string) $one['action_type'];
            $one['vid'] = (string) $one['vid'];
            return [$one];
        }
        return [];
    }

    public function getAds($flag='',$groupByPosition=false): array
    {
        $getAds = Cache::get('ads_key_'.$flag);
        $ads = $getAds ? $getAds->toArray() : [];
        $domain = VideoTrait::getDomain(env('SFTP_SYNC',1));
        $_v = date('YmdH');
        $filterAds = [];
        foreach ($ads as &$ad){
            if($ad['status']==1){
                $ad['img'] = $this->transferImgOut($ad['img'],$domain,$_v,'auto');
                $ad['action_type'] = (string)$ad['action_type'];
                $ad['vid'] = (string)$ad['vid'];
                $filterAds[] = $ad;
            }
        }
        if($groupByPosition){ //有位置的多一维
            $newAds = [];
            foreach ($filterAds as $item){
                $newAds[$item['position']][]= $item;
            }
            $filterAds = $newAds;
        }
        return !empty($filterAds) ? $filterAds : [];
    }

    public function insertAds($data, $flag='', $usePage=false, $page=1, $perPage=6): array
    {
        $adSet = cache()->get('ad_set');
        if (!$adSet) {
            return $data;
        }
        $res = $data;
        $rawPos = $adSet[$flag]['position'];
        if ($rawPos == 0) {
            $ads = $this->getAds($flag,$usePage);
            foreach ($res as $k=>$v){
                $tmpK = $usePage ? (($page-1) * $perPage + $k) : $k;
                $res[$k]['ad_list'] = $ads[$tmpK] ?? [];
            }
            return $res;
        } else {
            $ads = $this->getAds($flag);
        }
        $position = explode(':',$rawPos);
        $adCount = count($ads);
        if ($position[1]??false) {
            $position = rand($position[0],$position[1]);
        } else {
            // 不启用分组
            $position = $position[0];
        }
        $counter = 0;
        unset($k,$v);
        foreach ($res as $k=>$v){
            $cur = ($page-1) * $perPage + $k + 1;
            if ($position != 0 && $adCount>0) {
                if (($cur % $position == 0) && ($cur != 0)) {
                    $adsKey = $counter%$adCount;
                    $counter++;
                    $res[$k]['ad_list'] = [];
                    $tmpAd = $ads[$adsKey]??[];
                    if ($tmpAd) {
                        $res[$k]['ad_list'] = [$tmpAd];
                    }
                } else {
                    $res[$k]['ad_list'] = [];
                }
                continue;
            }
            $tmpK = $usePage ? $cur : $k;
            $res[$k]['ad_list'] = $ads[$tmpK] ?? [];
        }
        return $res;
    }

    public function frontFilterAd(&$Items): array
    {
        $ads = [];
        $nowTime = time();
        foreach ($Items as $ad){
            if($ad['status']==1){
                if(!$ad['end_at']){
                    $ads[] = $ad;
                } elseif ($nowTime < strtotime($ad['end_at'])){
                    $ads[] = $ad;
                }
            }
        }
        $Items = $ads;
        return $ads;
    }

}
