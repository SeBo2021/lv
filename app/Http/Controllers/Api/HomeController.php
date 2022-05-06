<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carousel;
use App\Models\Category;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    use PHPRedisTrait, GoldTrait, VideoTrait, AdTrait, MemberCardTrait;

    public function category(Request $request)
    {
        $categoryApiKey = 'api_category';
        $cacheData = $this->redis()->get($categoryApiKey);
        if($cacheData){
            $data = json_decode($cacheData,true);
        }else{
            $data = Category::query()
                ->where('parent_id',2)
                ->where('is_checked',1)
                ->orderBy('sort')
                ->get(['id','name','sort'])
                ->toArray();
        }
        return response()->json([
            'state'=>0,
            'data'=>$data
        ]);
    }

    //轮播

    /**
     * @throws ValidationException
     */
    public function carousel(Request $request): \Illuminate\Http\JsonResponse
    {
        if(isset($request->params)){
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params,[
                'cid' => 'required|integer'
            ])->validated();
            $cid = $validated['cid'];
            // Log::info('==carouselLog===',[$validated]);

            $configKey = 'api_carousel_'.$cid;
            $carouselData = $this->redis()->get($configKey);
            if (!$carouselData) {
                $data = Carousel::query()
                    ->where('status', 1)
                    ->where('cid', $cid)
                    ->get(['id','title','img','url','action_type','vid','status','end_at'])
                    ->toArray();
                $domain = self::getDomain(env('SFTP_SYNC',1));
                foreach ($data as &$item){
                    $item['img'] = $this->transferImgOut($item['img'],$domain,date('Ymd'),'auto');
                    $item['action_type'] = (string) $item['action_type'];
                    $item['vid'] = (string) $item['vid'];
                }
                $this->redis()->set($configKey,json_encode($data,JSON_UNESCAPED_UNICODE));
            } else {
                $data = json_decode($carouselData,true);
            }

            $res = [];
            $nowTime = time();
            foreach ($data as $carousel){
                if(!$carousel['end_at']){
                    $res[] = $carousel;
                } elseif ($nowTime < strtotime($carousel['end_at'])){
                    $res[] = $carousel;
                }
            }

            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return response()->json([]);
    }

    /**
     * @throws ValidationException
     */
    public function lists(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if(isset($request->params)){
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params,[
                'cid' => 'required|integer',
                'page' => 'required|integer',
            ])->validated();
            $cid = $validated['cid'];
            $page = $validated['page'];
        }else{
            return response()->json([]);
        }
        $redis = $this->redis();
        $sectionKey = ($this->apiRedisKey['home_lists']).$cid.'-'.$page;

        //二级分类列表
        $res = $redis->get($sectionKey);
        $perPage = 4;
        if(!$res){
            $paginator = Category::query()
                ->where('parent_id',$cid)
                ->where('is_checked',1)
                ->orderBy('sort')
                ->simplePaginate($perPage,['id','name','seo_title as title','is_rand','is_free','limit_display_num','group_type as style','group_bg_img as bg_img','local_bg_img','sort'],'',$page);
            $secondCateList = $paginator->toArray();
            $data = $secondCateList['data'];

            //加入视频列表
            //$queryBuild = Video::search('*')->where('status',1);
            foreach ($data as &$item)
            {
                //获取模块数据
                /**/
                $ids = $redis->sMembers('catForVideo:'.$item['id']);
                if(!empty($ids)){
                    /*$queryBuild = $queryBuild->whereIn('id',$ids);
                    $limit = $item['limit_display_num']>0 ? $item['limit_display_num'] : 8;
                    $videoList = $queryBuild->get()->toArray();
//                    $videoList = $queryBuild->simplePaginate($limit, 'searchPage', 1)->toArray()['data'];
                    if($item['is_rand']==1){
                        shuffle($videoList);
                    }else{
                        arrayDataMultiSort($videoList,[
                            'sort' => 'desc',
                            'updated_at' => 'desc',
                            'id' => 'desc',
                        ]);
                    }
                    $videoList = array_slice($videoList,0,$limit);
                    //Log::info('===TestSmallVideoList2==',$videoList);
                    $videoList = $this->handleVideoItems($videoList,false,$user->id);
                    $item['small_video_list'] = $videoList;

                    if(!empty($item['bg_img'])){
                        $item['bg_img'] = env('APP_URL').$item['bg_img'];
                    }*/
                    //$queryBuild = Video::search()->where('status',1)->whereIn('id',$ids);
//                    $queryBuild = Video::search()->where('status',1)->whereIn('id',$ids);
                    $queryBuild = DB::table('video')->where('status',1)->whereIn('id',$ids);
                    if($item['is_rand']==1){
                        $queryBuild = $queryBuild->inRandomOrder();
                    }else{
                        $queryBuild = $queryBuild->orderByRaw('video.sort DESC,video.updated_at DESC,video.id DESC');
                    }
                    $limit = $item['limit_display_num']>0 ? $item['limit_display_num'] : 8;
                    $videoList = $queryBuild->limit($limit)->get($this->videoFields)->toArray();
                    $videoList = $this->handleVideoItems($videoList,false,$user->id);
                    $item['small_video_list'] = $videoList;
                }
            }
            $res['hasMorePages'] = $paginator->hasMorePages();
            $res['list'] = $data;
            //广告
            $res['list'] = $this->insertAds($res['list'],'home_page',true,$page,$perPage);
            //存入redis
            $redis->set($sectionKey,json_encode($res,JSON_UNESCAPED_UNICODE));
            $redis->expire($sectionKey,3600);
        }else{
            $res = json_decode($res,true);
        }
        return response()->json([
            'state'=>0,
            'data'=>$res
        ]);
    }

}
