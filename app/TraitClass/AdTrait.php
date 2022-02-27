<?php

namespace App\TraitClass;

use App\Models\Ad;
use App\Models\AdSet;

trait AdTrait
{
    use AboutEncryptTrait;

    public function weightGet($flag=''): array
    {
        $ads = Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->get(['id','name','weight','title','img','position','url','play_url','type','status','action_type','vid','end_at'])
            ->toArray();
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
            $domain = env('RESOURCE_DOMAIN');
            //$one['img'] = $domain . $one['img'];
            //图片处理
            $_v = date('Ymd');
            $one['img'] = $this->transferImgOut($one['img'],$domain,$_v,'auto');
            $one['action_type'] = (string) $one['action_type'];
            $one['vid'] = (string) $one['vid'];
            return [$one];
        }
        return [];
    }

    /*public function get($flag='',$groupByPosition=false): array
    {
        $ads = Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->orderBy('sort')
            ->get(['id','sort','name','title','img','position','url','play_url','type','status','action_type','vid','end_at'])
            ->toArray();
        $domain = env('RESOURCE_DOMAIN');
        $_v = date('Ymd');
        foreach ($ads as &$ad){
            //$ad['img'] = $domain . $ad['img'];
            //图片处理
            $ad['img'] = $this->transferImgOut($ad['img'],$domain,$_v,'auto');
            $ad['action_type'] = (string)$ad['action_type'];
            $ad['vid'] = (string)$ad['vid'];
        }
        if($groupByPosition){ //有位置的多一维
            $newAds = [];
            foreach ($ads as $item){
                $newAds[$item['position']][]= $item;
            }
            $ads = $newAds;
        }
        return !empty($ads) ? $ads : [];
    }*/

    public function getAds($flag='',$groupByPosition=false): array
    {
        $ads = Ad::query()
            ->where('name',$flag)
            ->where('status',1)
            ->orderBy('sort')
            ->get(['id','sort','name','title','img','position','url','play_url','type','status','action_type','vid','end_at'])
            ->toArray();
        $domain = env('RESOURCE_DOMAIN');
        $_v = date('Ymd');
        foreach ($ads as &$ad){
            //$ad['img'] = $domain . $ad['img'];
            //图片处理
            $ad['img'] = $this->transferImgOut($ad['img'],$domain,$_v,'auto');
            $ad['action_type'] = (string)$ad['action_type'];
            $ad['vid'] = (string)$ad['vid'];
        }
        if($groupByPosition){ //有位置的多一维
            $newAds = [];
            foreach ($ads as $item){
                $newAds[$item['position']][]= $item;
            }
            $ads = $newAds;
        }
        return !empty($ads) ? $ads : [];
    }

    public function insertAds($data, $flag='', $usePage=false, $page=1, $perPage=6): array
    {
        $adSet = cache()->get('ad_set');
        if (!$adSet) {
            $adSet = array_column(AdSet::get()->toArray(),null,'flag');
            cache()->set('ad_set',$adSet);
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

}
