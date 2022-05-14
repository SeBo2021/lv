<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoShort;
use App\Models\ViewRecord;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use App\TraitClass\VideoTrait;
use App\TraitClass\VipRights;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoShortController extends Controller
{
    use VideoTrait,PHPRedisTrait,VipRights,StatisticTrait,MemberCardTrait,ApiParamsTrait;

    private array $mainCateAlias = [
        'short_hot',
        'limit_free',
        'short_rec'
    ];

    private array $cateMapAlias = [
        '-1' => 'sub_cat_1',
        '-2' => 'sub_cat_2',
        '-3' => 'sub_cat_3',
        '-4' => 'sub_cat_4',
        '-5' => 'sub_cat_5',
        '-6' => 'sub_cat_6',
        '-7' => 'sub_cat_7',
        '-8' => 'sub_cat_8',
    ];

    /**
     * 视频分类
     * @param Request $request
     * @return JsonResponse
     */
    public function cate(Request $request): JsonResponse
    {
        $cacheKey = 'short_category';
        $cacheData = $this->redis()->get($cacheKey);
        if($cacheData){
            $data = json_decode($cacheData,true);
        }else{
            $raw = Category::whereIn('mask', $this->mainCateAlias)
                ->where('is_checked',1)
                ->orderBy('sort', 'desc')
                ->select('id', 'name')
                ->get();
            $data = json_decode($raw, true);
            $this->redis()->set($cacheKey,json_encode($data));
        }

        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }

    /**
     * 读取数据
     * @param $page
     * @param $user
     * @param $startId
     * @param $cateId
     * @param $tagId
     * @param $words
     * @return array
     */
    private function items($page, $user, $startId,$cateId,$tagId,$words): array
    {
        $redis = $this->redis();
        if ($page == 1) {
            if ($cateId) {
                $ids = $this->redis()->get("shortVideoCateIds_{$cateId}")?:'';
            } else {
                $ids = $this->redis()->get('shortVideoIds')?:'';
                $cateId = 0;
            }
            $redisIds = explode(',',$ids);
            shuffle($redisIds);
            $newIds = implode(',',$redisIds);
            $redis->set("newShortVideoByUid_{$cateId}_{$user->id}",$newIds);
        } else {
            $newIds = $redis->get("newShortVideoByUid_{$cateId}_{$user->id}");
        }
        $videoField = ['id', 'name', 'cid', 'cat','tag', 'restricted', 'sync', 'title', 'url', 'dash_url', 'hls_url', 'gold', 'duration', 'type',  'views', 'likes', 'comments', 'cover_img', 'updated_at'];
        $perPage = 8;
        //$model = VideoShort::query()->where('status',1);
        $model = VideoShort::search("*")->where('status',1);

        if ($tagId) {
            $tagInfo = Tag::query()->where(['mask'=>$this->cateMapAlias[$tagId]])->firstOrFail()?->toArray();
            if(!empty($tagInfo)){
                $model = VideoShort::search('"'.$tagInfo['id'].'"')->where('status',1);
            }
        }else{
            if ($cateId) {
                $model = VideoShort::search('"'.$cateId.'"')->where('status',1);
            }
        }
        if ($startId) {
            $model = $model->where('id','<=',$startId)->orderBy('id','desc');
        }

        $items = [];
        if(!empty($words)){
            $model = VideoShort::search($words)->where('status', 1);
            $paginator =$model->simplePaginate($perPage, 'searchPage', $page);
            $items = $paginator->items();
            $more = $paginator->hasMorePages();
        }else {
            if ($newIds && (!$tagId) && (!$startId)) {
                $cacheIds = explode(',', $newIds);
                $start = $perPage * ($page - 1);
                $ids = array_slice($cacheIds, $start, $perPage);
                foreach ($ids as $id) {
                    $mapNum = $id % 300;
                    $cacheKey = "short_video_$mapNum";
                    $raw = $this->redis()->hGet($cacheKey, $id);
                    if ($raw) {
                        $items[] = json_decode($raw, true);
                    }
                }
                $more = false;
                if (count($ids) == $perPage) {
                    $more = true;
                }
            } else {
                $paginator = $model->simplePaginate($perPage, 'shortLists', $page);
                $items = $paginator->items();
                $more = $paginator->hasMorePages();
            }
        }

        $data = [];
        $_v = date('Ymd');
        $isVip = $this->isVip($user);
        foreach ($items as $one) {
            $one['limit'] = 0;
            if ($one['restricted'] == 1  && (!$isVip)) {
                $one['limit'] = 1;
            }
            $viewRecord = $this->isShortLoveOrCollect($user->id, $one['id']);
            $one['is_love'] = isset($viewRecord['is_love']) ? $viewRecord['is_love']+=0 : 0;
            $sync = $one['sync'] ?? 2;
            $sync = $sync>0 ? $sync : 2;
            $resourceDomain = self::getDomain($sync);
            //是否收藏
            $one['is_collect'] = isset($viewRecord['is_collect']) ? $viewRecord['is_collect']+=0 : 0;
            $one['url'] = $resourceDomain  .$one['url'];
            $one['dash_url'] = $resourceDomain  .$one['dash_url'];
            $one['cover_img'] = $this->transferImgOut($one['cover_img'],$resourceDomain,$_v);
            //hls处理
            $one['hls_url'] = $resourceDomain .$this->transferHlsUrl($one['hls_url'],$one['id'],$_v);
            $data[] = $one;
        }

        return [
            'list' => $data,
            'hasMorePages' => $more,
        ];
    }

    /**
     * 观看限制判断
     * @param $one
     * @param $user
     * @return mixed
     */
    public function viewLimit($one, $user): mixed
    {
        /*if($user->long_vedio_times<1){ //没有免费观看次数再限制
            if ($one['restricted'] == 1) {
                if ((!$user->member_card_type) && (time() - $user->vip_expired > $user->vip_start_last)) {
                    $one['limit'] = 1;
                }
            }
        }*/
        if ($one['restricted'] == 1  && (!$this->isVip($user))) {
            $one['limit'] = 1;
        }
        return $one;
    }

    /**
     * 播放
     * @param Request $request
     * @return JsonResponse
     */
    public function lists(Request $request): JsonResponse
    {
        // 业务逻辑
        if (isset($request->params)) {
            $params = self::parse($request->params);
            $validated = Validator::make($params, [
                'start_id' => 'nullable',
                'keyword' => 'nullable',
                'cate_id' => 'nullable',
                'tag_id' => 'nullable',
                'sort' => 'nullable',
                'use_gold' => [
                    'nullable',
                    'string',
                    Rule::in(['1', '0']),
                ],
            ])->validated();
            $user = $request->user();
            $page = $params['page'] ?? 1;
            $cateId = $params['cate_id'] ?? "";
            $tagId = $params['tag_id'] ?? "";
            $starId = $validated['start_id'] ?? '0';
            //关键词搜索
            $words = $params['keyword'] ?? '';
            if (!empty($words)) {
                $cateId = "";
                $tagId = "";
                $starId = '0';
            }
            try {
                $res = $this->items($page, $user, $starId, $cateId, $tagId, $words);
                return response()->json(['state' => 0, 'data' => $res], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
            } catch (Exception $exception) {
                $msg = $exception->getMessage();
                Log::error("shortLists", [$msg]);
                return response()->json(['state' => -1, 'data' => $msg], 200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);
            }
        }
        return response()->json(['state'=>-1, 'msg'=>'参数错误'],200, ['Content-Type' => 'application/json;charset=UTF-8','Charset' => 'utf-8']);

    }

    /**
     * 点赞
     * @param Request $request
     * @return JsonResponse
     */
    public function like(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $params = self::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'like' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['like'];
            $redis = $this->redis();
            if ($is_love) {
                $one['is_love'] = $redis->set("short_is_love_{$user->id}_{$id}", 1);
                VideoShort::query()->where('id', $id)->increment('likes');
            } else {
                $one['is_love'] = $redis->del("short_is_love_{$user->id}_{$id}", 0);
                VideoShort::query()->where('id', $id)->decrement('likes');
            }
            $attributes = ['uid' => $user->id, 'vid' => $id];
            $values = ['is_love' => $is_love];
            ViewRecord::query()->updateOrInsert($attributes, $values);
            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionLike", [$msg]);
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);
        }
    }

    /**
     * 收藏
     * @param Request $request
     * @return JsonResponse
     */
    public function collect(Request $request): JsonResponse
    {
        if(!$this->collectRight($request->user())){
            return response()->json([
                'state' => -2,
                'msg' => "权限不足",
            ]);
        }
        try {
            $userInfo = $request->user();
            $params = self::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'collect' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $isCollect = $params['collect'];
            $redis = $this->redis();

            if ($isCollect) {
                $redis->set("short_is_collect_{$userInfo->id}_{$id}", 1);
                VideoShort::query()->where('id', $id)->increment('favors');
            } else {
                $redis->del("short_is_collect_{$userInfo->id}_{$id}");
                VideoShort::query()->where('id', $id)->decrement('favors');
            }

            $attributes = ['uid' => $userInfo->id, 'vid' => $id];
            $values = ['is_collect' => $isCollect,'usage'=>2,'time_at'=>time()];
            ViewRecord::query()->updateOrInsert($attributes, $values);
            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            file_put_contents("1.txt",$msg,FILE_APPEND);
            Log::error("actionCollect", [$msg]);
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);
        }
    }

    /**
     * 判断是否收藏或喜欢
     * @param int $uid
     * @param $vid
     * @return int[]
     */
    public function isShortLoveOrCollect($uid = 0, $vid = 0): array
    {
        $redis = $this->redis();
        $one['is_love'] = $redis->get("short_is_love_{$uid}_{$vid}") ?: 0;
        //是否收藏
        $one['is_collect'] = $redis->get("short_is_collect_{$uid}_{$vid}") ?: 0;
        return $one;
    }

}