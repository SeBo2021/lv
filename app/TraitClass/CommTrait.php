<?php

namespace App\TraitClass;

use App\Models\Category;
use App\Models\CommCate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CommTrait
{
    use PHPRedisTrait;
    public function getCommCate()
    {
        $raw = $this->redis()->get('common_cate');
        $data = @json_decode($raw, true);
        $data = $data ?? [];
        if(!empty($data)){
            return $data;
        }
        //$data = [];
        $raw = CommCate::query()->orderBy('order', 'desc')
            ->select('id','name','parent_id','mark','order','is_allow_post','can_select_city')
            ->get()->toArray();
        foreach ($raw as $v1) {
            $this->redis()->hSet('common_cate_help', "c_{$v1['id']}", $v1['mark']);
            if ($v1['parent_id'] == 0) {
                $data[] = $v1;
            };
        }
        foreach ($raw as $v2) {
            if ($v2['parent_id'] > 0) {
                $this->redis()->hSet('common_cate_help', "c_{$v2['id']}", $v2['mark']);
                foreach ($data as $k3=>$v3) {
                    if ($v2['parent_id'] == $v3['id']) {
                        $data[$k3]['childs'][] = $v2;
                    }
                }
            }
        }
        $this->redis()->set('common_cate',json_encode($data));
        return $data;
    }

    public function resetHomeCategory()
    {
        Cache::forever('api_home_category',Category::query()
            ->where('parent_id',2)
            ->where('is_checked',1)
            ->orderBy('sort')
            ->get(['id','name','sort']));
    }

    public function getHomeCategory()
    {
        return Cache::rememberForever('api_home_category',function (){
            return Category::query()
                ->where('parent_id',2)
                ->where('is_checked',1)
                ->orderBy('sort')
                ->get(['id','name','sort']);
        });
    }

    public function resetHomeRedisData()
    {
        $homeCats = $this->getHomeCategory();
        $redis = $this->redis();
        $perPage = 4;
        $page = 1;
        foreach ($homeCats as $homeCat){
            $blockCat = Category::query()
                ->where('parent_id',$homeCat->id)
                ->where('is_checked',1)
                ->orderBy('sort')
                ->simplePaginate($perPage,['id','name','seo_title as title','is_rand','is_free','limit_display_num','group_type as style','group_bg_img as bg_img','local_bg_img','sort'],'',$page);
            if(!$blockCat->hasMorePages()){
                break;
            }
            foreach ($blockCat as &$item){
                //获取模块数据
                $ids = $redis->sMembers('catForVideo:'.$item['id']);
                if(!empty($ids)){
                    $videoBuild = DB::table('video')->where('status',1)->whereIn('id',$ids);
                    if($item['is_rand']==1){
                        $videoBuild = $videoBuild->inRandomOrder();
                    }else{
                        $videoBuild = $videoBuild->orderByRaw('video.sort DESC,video.updated_at DESC,video.id DESC');
                    }
                    $limit = $item['limit_display_num']>0 ? $item['limit_display_num'] : 8;
                    $videoList = $videoBuild->limit($limit)->get($this->videoFields)->toArray();
                    $item['small_video_list'] = $videoList;
                }
            }

            //广告
            $blockCat = $this->insertAds($blockCat,'home_page',true,$page,$perPage);
            //存入redis
            $sectionKey = ($this->apiRedisKey['home_lists']).$homeCat->id.'-'.$page;
            $redis->set($sectionKey,json_encode($blockCat,JSON_UNESCAPED_UNICODE));
            $redis->expire($sectionKey,7200);
            ++$page;
        }

    }
}