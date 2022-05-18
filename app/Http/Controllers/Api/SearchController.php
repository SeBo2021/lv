<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\UpdateKeyWords;
use App\Models\Category;
use App\Models\KeyWords;
use App\Models\Tag;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Elasticsearch\ClientBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\CidVid;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    use VideoTrait,PHPRedisTrait,AdTrait,ApiParamsTrait;

    /**
     * 搜索功能
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function index(Request $request)
    {
        if (isset($request->params)) {
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'words' => 'nullable',
                'page' => 'required|integer',
                "cid" => 'array',// 分类
                "bid" => 'array',// 版块
                "tag" => 'array', // 标签
                "type" => 'nullable', // 类型
                "sort" => 'nullable', // 排序
            ])->validate();
            $perPage = 16;
            $cats =$params['cid']??[];
            $bids = $params['bid']??[];
            $vIds = $this->getAllVid($cats,$bids);
            $page = $validated['page'];
            $order = $this->getOrderColumn(isset($validated['sort']) ? (string)$validated['sort'] : -1);
            $type = $validated['type']??-1;
            $words = $validated['words']??false;

            $model = Video::search($words?:"*")->where('status', 1);
            // 分类
            if (!empty($vIds)) {
                $model->whereIn('id',$vIds);
            }
            // 类别
            if ($type != -1) {
                $model->where('restricted',$type);
            }
            // 排序
            if ($order) {
                $model->orderBy($order,'desc');
            }
            // 标签 预留
            $paginator =$model->simplePaginate($perPage, 'searchPage', $page);
            $paginatorArr = $paginator->toArray()['data'];

            //$client = ClientBuilder::create()->build();
            $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);

            $res['hasMorePages'] = $paginator->hasMorePages();
            if ($words) {
                UpdateKeyWords::dispatchAfterResponse($validated['words']);
            }
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return [];
    }

    //标签
    public function tag(Request $request): JsonResponse
    {
        if(isset($request->params)){
            $perPage = 16;
            $params = self::parse($request->params);
            if (isset($params['pageSize']) && ($params['pageSize'] < $perPage)) {
                $perPage = $params['pageSize'];
            }
            $page = $params['page'] ?? 1;
            $id = $params['id'] ?? 0;
            $paginator = DB::table('tid_vid')
                ->join('video','tid_vid.vid','=','video.id')
                ->where('tid_vid.tid',$id)
                ->where('video.status',1)
                ->simplePaginate($perPage,$this->videoFields,'tag',$page);
            $res['list'] = $this->handleVideoItems($paginator->toArray()['data'],false,$request->user()->id);
            $res['hasMorePages'] = $paginator->hasMorePages();
            DB::table('tag')->where('id',$id)->increment('hits');
            //$this->redis()->del($this->apiRedisKey['hot_tags']);
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);

        }
        return response()->json([]);
    }

    //更多

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function cat(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'cid' => 'required|integer',
                    'page' => 'required|integer',
                ])->validated();
                $cid = $validated['cid'];
                $page = $validated['page'];

                $redisKey = $this->apiRedisKey['search_cat'].$cid.'-'.$page;
                $redis = $this->redis();
                $res = $redis->get($redisKey);
                if(!$res){
                    $perPage = 16;
                    $paginator = Video::search('"'.$cid.'"')->where('status',1)->simplePaginate($perPage,'searchCat',$page);

                    $paginatorArr = $paginator->toArray()['data'];
                    if(!empty($paginatorArr)){
                        $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);
                        //广告
                        $res['list'] = $this->insertAds($res['list'],'more_page',true, $page, $perPage);
                        //Log::info('==CatList==',$res['list']);
                        $res['hasMorePages'] = $paginator->hasMorePages();
                        $redis->set($redisKey,json_encode($res,JSON_UNESCAPED_UNICODE));
                        $redis->expire($redisKey,3600);
                    }
                }else{
                    $res = json_decode($res,true);
                }
                if(isset($res['list']) && !empty($res['list'])){
                    foreach ($res['list'] as $d){
                        if(!empty($d['ad_list'])){
                            $this->frontFilterAd($d['ad_list']);
                        }
                    }
                }
                return response()->json(['state'=>0, 'data'=>$res]);
            }
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }
        return response()->json([]);
    }

    //推荐

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function recommend(Request $request): JsonResponse
    {
        try {
            if(isset($request->params)){
                $params = self::parse($request->params);
                $validated = Validator::make($params,[
                    'vid' => 'required|integer',
                ])->validated();
                $page = $validated['page'] ?? 1;
                $perPage = 9;
                $vid = $validated['vid'];
                $cat = $this->getVideoById($vid)->cat;
                $res = ['list'=>[], 'hasMorePages'=>false];
                if(!empty($cat)){
                    $key = 'searchRecommend:'.$cat.':'.$page;
                    $redis = $this->redis();
                    $redisJsonData = $redis->get($key);
                    if(!$redisJsonData){
                        $keyWordsArr = (array)@json_decode($cat,true);
                        $keyWords = implode(',',$keyWordsArr);
                        $paginator = Video::search($keyWords)->where('status',1)->simplePaginate($perPage,'searchCat',$page);
                        $paginatorArr = $paginator->toArray()['data'];
                        foreach ($paginatorArr as $key=>$value){
                            if($value['id']==$vid){
                                unset($paginatorArr[$key]);
                            }
                        }
                        if(!empty($paginatorArr)){
                            $res['list'] = $this->handleVideoItems($paginatorArr,false,$request->user()->id);
                            //广告
                            $res['list'] = $this->insertAds($res['list'],'more_page',true, $page, $perPage);
                            $res['hasMorePages'] = false;
                            $redis->set($key,json_encode($res,JSON_UNESCAPED_UNICODE));
                            $redis->expire($key,3600);
                        }

                    }else{
                        $res = json_decode($redisJsonData,true);
                    }
                    if(isset($res['list']) && !empty($res['list'])){
                        foreach ($res['list'] as $d){
                            if(!empty($d['ad_list'])){
                                $this->frontFilterAd($d['ad_list']);
                            }
                        }
                    }
                }
                return response()->json(['state'=>0, 'data'=>$res]);
            }
            return response()->json(['state' => -1, 'msg' => "参数错误"]);
        }catch (\Exception $exception){
            return $this->returnExceptionContent($exception->getMessage());
        }

    }

    public function hotWords(): JsonResponse
    {
        $words = KeyWords::query()
            ->orderByDesc('hits')
            ->limit(8)
            ->pluck('words');
        return response()->json([
            'state'=>0,
            'data'=>$words
        ]);
    }

    public function hotTags(): JsonResponse
    {
        $redis = $this->redis();
        $res = $redis->get($this->apiRedisKey['hot_tags']);

        if(!$res){
            $tags = Tag::query()
                ->orderBy('hits','desc')
                ->limit(15)
                ->get(['id','name']);
        }else{
            $tags = json_decode($res,true);
        }

        if(!empty($tags)){
            $redis->set($this->apiRedisKey['hot_tags'],json_encode($tags,JSON_UNESCAPED_UNICODE));
            $redis->expire($this->apiRedisKey['hot_tags'],$this->redisExpiredTime);
        }
        return response()->json([
            'state'=>0,
            'data'=>$tags
        ]);
    }

    /**
     * 得到排序标识
     * @param string $sort
     * @return string
     */
    private function getOrderColumn(string $sort): string
    {
        return match ($sort) {
            '0' => 'views',
            '1' => 'id',
            '2' => 'collects',
            '3' => 'likes',
            default => '',
        };
    }

    /**
     * 得到搜索选项
     */
    public function getOption()
    {
        $data = Category::with('childs:id,name,parent_id')
            ->where('parent_id','2')
            ->where('is_checked',1)
            ->select('id','name','parent_id')
            ->orderBy('sort')
            ->get();
        return response()->json([
            'state'=>0,
            'data'=>$data
        ]);
    }

    /**
     * 得到搜索选项
     */
    private function getAllVid($cid, $bid = []): array
    {
        // 数组的起始值作为缓存key
        $cacheKey = sprintf('search_vid_%s_%s',$cid[0]??0 , $bid[0]??0);
        $vids = cache()->get($cacheKey)?:[];
        if (!$vids) {
            $cids = $bid;
            if ((!$cids) && $cid) {
                $data = Category::with('childs:id,name,parent_id')
                    ->where('is_checked', 1)
                    ->whereIn('parent_id', $cid)
                    ->select('id', 'name', 'parent_id')
                    ->pluck('id')->toArray();
                $cids = array_merge($data, $cid);
            }
            $vids = $data = CidVid::query()
                ->select('vid')
                ->distinct()
                ->whereIn('cid', $cids)
                ->pluck('vid')->toArray();
            cache()->set($cacheKey,$vids,30*60);
        }
        return $vids?:($bid?[-1]:[]);
        /*$catId = $cid[0]??0;
        $blockId = $bid[0]??0;
        $vidArr = [];
        $redis = $this->redis();
        if($catId>0){
            $catIds = Category::query()
                ->where('parent_id',$cid)
                ->where('is_checked',1)
                ->pluck('id')->all();
            foreach ($catIds as $id){
                $mergeArr = $redis->sMembers('catForVideo:'.$id) ?? [];
                $vidArr = array_merge($vidArr,$mergeArr);
            }
            $vidArr = array_unique($vidArr);
        }
        if($blockId>0){
            $vidArr = $redis->sMembers('catForVideo:'.$blockId);
        }
        return $vidArr;*/
    }
}
