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

    public function category(Request $request): \Illuminate\Http\JsonResponse
    {
        $cacheData = Cache::get('api_home_category');
        $data = $cacheData ? $cacheData->toArray() : [];
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
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'cid' => 'required|integer'
                ])->validated();
                $cid = $validated['cid'];
                // Log::info('==carouselLog===',[$validated]);
                $configKey = 'api_carousel_'.$cid;
                $redis = $this->redis();
                $carouselData = $redis->get($configKey);
                if($carouselData){
                    $data = json_decode($carouselData,true);
                    $res = $this->frontFilterAd($data);
                    return response()->json([
                        'state'=>0,
                        'data'=>$res
                    ]);
                }
            }
        }catch (\Exception $exception){
            $msg = $exception->getMessage();
            Log::error("api/carousel", [$msg]);
            return response()->json(['state' => -1, 'msg' => $msg,'data'=>[]], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
        }

        return response()->json(['state' => -1, 'msg' => "参数错误",'data'=>[]], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
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
            if(isset($res['list'])){
                foreach ($res['list'] as &$r){
                    if(!empty($r['ad_list'])){
                        $this->frontFilterAd($r['ad_list']);
                    }
                    if(!empty($r['small_video_list'])){
                        $r['small_video_list'] = $this->handleVideoItems($r['small_video_list'],false,$user->id);
                    }
                }
                return response()->json(['state'=>0, 'data'=>$res]);
            }
            return response()->json(['state'=>0, 'data'=>[]]);
        }
        return response()->json(['state' => -1, 'msg' => "参数错误"], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
    }

}
