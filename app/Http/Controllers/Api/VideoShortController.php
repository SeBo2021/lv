<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoShort;
use App\Models\ViewRecord;
use App\TraitClass\ApiParamsTrait;
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
    use VideoTrait;
    use PHPRedisTrait;
    use VipRights;
    use StatisticTrait;

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
        $raw = Category::whereIn('mask', $this->mainCateAlias)
            ->where('is_checked',1)
            ->orderBy('sort', 'desc')
            ->select('id', 'name')
            ->get();
        $data = json_decode($raw, true);
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
        $videoField = ['id', 'name', 'cid', 'cat','tag', 'restricted', 'sync', 'title', 'url', 'dash_url', 'hls_url', 'gold', 'duration', 'type',  'views', 'likes', 'comments', 'cover_img', 'updated_at'];
        $perPage = 8;
        $model = VideoShort::query()->where('status',1);
        $listIsRand = false;

        if ($tagId) {
            $tagInfo = Tag::query()->where(['mask'=>$this->cateMapAlias[$tagId]])->firstOrFail()?->toArray();
            if(!empty($tagInfo)){
                $tagWord = sprintf('"%s"',$tagInfo['id']);
                $model = $model->where('tag','like',"%{$tagWord}%");
            }
        }else{
            if ($cateId) {
                //$listIsRand = Category::query()->where('id',$cateId)->value('is_rand')==1;
                $cateWord = sprintf('"%s"',$cateId);
                $model = $model->where('cat','like',"%{$cateWord}%");
            }
        }
        if ($startId) {
//            $model = $model->where('id','>=',$startId);
            $model = $model->where('id','<=',$startId);
        }
        if(!empty($words)){
            $model = VideoShort::search($words)->where('status', 1);
            $paginator =$model->simplePaginate($perPage, 'searchPage', $page);
        }else{
            //是否随机
            /*if($listIsRand){
                if($page == 1){
                    $model = $model->inRandomOrder();
                    $modelStr = serialize($model);
                    DB::table('short_video_model')->where('uid',$user->id)->where('cate_id',$cateId)->delete();
                    DB::table('short_video_model')->insert(['uid'=>$user->id,'cate_id'=>$cateId,'short_serialize' =>$modelStr]);
                }else{
                    $short_serialize = DB::table('short_video_model')->where('uid',$user->id)->where('cate_id',$cateId)->value('short_serialize');
                    $model = $short_serialize ? unserialize($short_serialize) : $model;
                }
            }*/
            $model = $model->orderByDesc('id');
            $paginator = $model->simplePaginate($perPage, $videoField, 'shortLists', $page);
        }
        $items = $paginator->items();

        $data = [];
        foreach ($items as $one) {
            //  $one = $this->handleShortVideoItems([$one], true)[0];
            $one['limit'] = 0;
            $one = $this->viewLimit($one, $user);
            $viewRecord = $this->isShortLoveOrCollect($user->id, $one['id']);
            $one['is_love'] = intval($viewRecord['is_love']) ?? 0;
            $resourceDomain = env('RESOURCE_DOMAIN');
            //是否收藏
            $one['is_collect'] = intval($viewRecord['is_collect']) ?? 0;
            $one['url'] = $resourceDomain  .$one['url'];
            $one['hls_url'] = $resourceDomain  .$one['hls_url'];
            $one['dash_url'] = $resourceDomain  .$one['dash_url'];
            $one['cover_img'] = $resourceDomain . $one['cover_img'];

            $data[] = $one;
        }

        return [
            'list' => $data,
            'hasMorePages' => $paginator->hasMorePages()
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
        if($user->long_vedio_times<1){
            if ($one['restricted'] == 1) {
                if ((!$user->member_card_type) && (time() - $user->vip_expired > $user->vip_start_last)) {
                    $one['limit'] = 1;
                }
            }
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
        try {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);

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

            $page = $params['page'] ?? 1;
            $cateId = $params['cate_id'] ?? "";
            $tagId = $params['tag_id'] ?? "";
            $starId = $validated['start_id'] ?? '0';
            //关键词搜索
            $words = $params['keyword'] ?? '';
            if(!empty($words)){
                $cateId = "";
                $tagId = "";
                $starId = '0';
            }
            $res = $this->items($page, $user, $starId,$cateId,$tagId,$words);
            //Log::info('==ShortVideo==',[$params,$user->id,$res]);
            //统计激活视频人数===============
            $this->saveUsersDay($user->id, $user->channel_id, $user->device_system);
            //============================
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);

        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("shortLists", [$msg]);
            return response()->json([
                'state' => -1,
                'data' => $msg
            ]);
        }
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
            $params = ApiParamsTrait::parse($request->params);
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
            $params = ApiParamsTrait::parse($request->params);
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