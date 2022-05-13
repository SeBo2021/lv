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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    use PHPRedisTrait, GoldTrait, VideoTrait, AdTrait, MemberCardTrait,ApiParamsTrait;

    public function category(Request $request)
    {
        $cacheData = Cache::get('api_home_category');
        if($cacheData){
            $data = $cacheData->toArray();
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
            $params = self::parse($request->params);
            $validated = Validator::make($params,[
                'cid' => 'required|integer'
            ])->validated();
            $cid = $validated['cid'];
            // Log::info('==carouselLog===',[$validated]);

            $configKey = 'api_carousel_'.$cid;
            $carouselData = $this->redis()->get($configKey);
            $domain = self::getDomain(env('SFTP_SYNC',1));
            if (!$carouselData) {
                $data = Carousel::query()
                    ->where('status', 1)
                    ->where('cid', $cid)
                    ->get(['id','title','img','url','action_type','vid','status','end_at'])
                    ->toArray();
                $this->redis()->set($configKey,json_encode($data,JSON_UNESCAPED_UNICODE));
            } else {
                $data = json_decode($carouselData,true);
            }

            $res = [];
            $nowTime = time();
            foreach ($data as $carousel){
                $carousel['img'] = $this->transferImgOut($carousel['img'],$domain,date('Ymd'),'auto');
                $carousel['action_type'] = (string) $carousel['action_type'];
                $carousel['vid'] = (string) $carousel['vid'];
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
            $params = self::parse($request->params);
            $validated = Validator::make($params,[
                'cid' => 'required|integer',
                'page' => 'required|integer',
            ])->validated();
            $cid = $validated['cid'];
            $page = $validated['page'];
            $redis = $this->redis();
            $sectionKey = ($this->apiRedisKey['home_lists']).$cid.'-'.$page;

            //二级分类列表
            $res = $redis->get($sectionKey);
            $res = json_decode($res,true);
            foreach ($res['list'] as &$r){
                if(!empty($r['small_video_list'])){
                    $r['small_video_list'] = $this->handleVideoItems($r['small_video_list'],false,$user->id);
                }
            }
            return response()->json(['state'=>0, 'data'=>$res, 200, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_UNESCAPED_UNICODE]);
        }
        return response()->json(['state' => -1, 'msg' => "参数错误"], 200, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_UNESCAPED_UNICODE);
    }

}
