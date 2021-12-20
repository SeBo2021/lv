<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\ProcessViewVideo;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Video;
use App\Models\VideoShort;
use App\Models\ViewRecord;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FakeLiveShortController extends Controller
{
    use VideoTrait;
    use PHPRedisTrait;

    /**
     * 视频分类
     * @param Request $request
     * @return JsonResponse
     */
    public function cate(Request $request): JsonResponse
    {
        $raw = Category::where('usage', '2')
            ->select('id', 'name')
            ->get();
        $data = json_decode($raw, true);
        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }

    private function items($page,$uid,$id) {
        $videoField = ['id', 'name', 'cid', 'cat', 'restricted', 'sync', 'title', 'url', 'gold', 'duration', 'hls_url', 'dash_url', 'type', 'cover_img', 'views', 'likes', 'comments', 'updated_at'];
        $perPage = 8;
        $paginator = VideoShort::query()
            ->simplePaginate($perPage, $videoField, 'shortLists', $page);
        $items = $paginator->items();
        $data = [];
        foreach ($items as $one) {
            $one = $this->handleShortVideoItems([$one], true)[0];
            $one['limit'] = 0;
            // 任何类型都有 是否点赞 is_collect 并增加观看记录
            // ProcessViewVideo::dispatchAfterResponse($user, $one);
            //是否点赞
            $viewRecord = $this->isShortLoveOrCollect($uid, $id);
            $one['is_love'] = $viewRecord['is_love'] ?? 0;
            //是否收藏
            $one['is_collect'] = $viewRecord['is_collect'] ?? 0;
            $data[] = $one;
        }
        return [
            'list' => $data,
            'hasMorePages' => $paginator->hasMorePages()
        ];
    }

    //播放
    public function lists(Request $request)
    {
        // 业务逻辑
        try {
            $uid = $request->user()->id;
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params, [
                'start_id' => 'nullable',
                'keyword' => 'nullable',
                'cate_id' => 'nullable',
                'sort' => 'nullable',
                'use_gold' => [
                    'nullable',
                    'string',
                    Rule::in(['1', '0']),
                ],
            ])->validated();
            $page = $params['page'] ?? 1;
            $id = $validated['id'] ?? '0';
            $res = $this->items($page,$uid,$id);
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

    //点赞
    public function like(Request $request)
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

    // 收藏
    public function collect(Request $request)
    {
        try {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'collect' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['collect'];
            $redis = $this->redis();


            if ($is_love) {
                $redis->set("short_is_collect_{$user->id}_{$id}", 1);
                Video::query()->where('id', $id)->increment('favors');
            } else {
                $redis->del("short_is_collect_{$user->id}_{$id}");
                Video::query()->where('id', $id)->decrement('favors');
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
        }
    }

    /**
     * 小视频处理
     * @param $lists
     * @param false $display_url
     * @param int $uid
     * @return mixed
     */
    public function handleShortVideoItems($lists, $display_url = false, $uid = 0)
    {

        foreach ($lists as &$item) {
            //$item = (array)$item;
            $domainSync = VideoTrait::getDomain($item['sync']);
            $item['cover_img'] = $domainSync . $item['cover_img'];
            $item['gold'] = $item['gold'] / $this->goldUnit;
            $item['views'] = $item['views'] > 0 ? $this->generateRandViews($item['views']) : $this->generateRandViews(rand(5, 9));
            $item['hls_url'] = $domainSync . $item['hls_url'];
            $item['preview_hls_url'] = $this->getPreviewPlayUrl($item['hls_url']);
            $item['dash_url'] = $domainSync . $item['dash_url'];
            $item['preview_dash_url'] = $this->getPreviewPlayUrl($item['dash_url'], 'dash');
            if (!$display_url) {
                unset($item['hls_url'], $item['dash_url']);
            }
            //是否点赞
            $viewRecord = $this->isShortLoveOrCollect($uid, $item['id']);
            $item['is_love'] = $viewRecord['is_love'] ?? 0;
            //是否收藏
            $item['is_collect'] = $viewRecord['is_collect'] ?? 0;
        }
        return $lists;
    }

    /**
     * 判断是否收藏或喜欢
     * @param int $uid
     * @param $vid
     * @return int[]
     */
    public function isShortLoveOrCollect($uid = 0, $vid = 0): array
    {
        $one = [
            'is_love' => 0,
            'is_collect' => 0,
        ];
        if (!$uid) {
            return $one;
        }
        /*$viewRecord = ViewRecord::query()->where('uid', $uid)->where('vid', $vid)->first(['id', 'is_love', 'is_collect']);
        //是否点赞
        $one['is_love'] = $viewRecord['is_love'] ?? 0;
        //是否收藏
        $one['is_collect'] = $viewRecord['is_collect'] ?? 0;*/
        $redis = $this->redis();

        $one['is_love'] = $redis->get("short_is_love_{$uid}_{$vid}") ?: 0;
        //是否收藏
        $one['is_collect'] = $redis->get("short_is_collect_{$uid}_{$vid}") ?: 0;

        return $one;
    }

    /**
     * 金豆判断
     * @param $one
     * @param $user
     * @return mixed
     */
    public function vipOrGold($one, $user): mixed
    {
        switch ($one['restricted']) {
            case 1:
                if ((!$user->member_card_type) && (time() - $user->vip_expired > $user->vip_start_last)) {
                    $one['limit'] = 1;
                }
                break;
            case 2:
                $redisHashKey = $this->apiRedisKey['user_gold_video'] . $user->id;
                $buy = $this->redis()->sIsMember($redisHashKey, $one['id']);
                if (!$buy) {
                    $one['limit'] = 2;
                }

        }
        return $one;
    }

}