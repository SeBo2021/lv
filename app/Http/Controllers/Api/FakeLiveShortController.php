<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Live;
use App\Models\Tag;
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
     * 读取数据
     * @param $page
     * @param $uid
     * @param $startId
     * @param $cateId
     * @param $tagId
     * @return array
     */
    private function items($page, $uid, $startId,$cateId,$tagId): array
    {
        $videoField = ['id', 'name', 'cid', 'cat','tag', 'restricted', 'sync', 'title', 'url', 'gold', 'duration', 'duration_seconds', 'type',  'views', 'likes', 'comments', 'cover_img', 'updated_at','intro','age', 'hls_url', 'dash_url'];
        $perPage = 8;
        $model = Live::query();
        $paginator = $model->simplePaginate($perPage, $videoField, 'shortLists', $page);
        $items = $paginator->items();
        $data = [];
        foreach ($items as $one) {
            //  $one = $this->handleShortVideoItems([$one], true)[0];
            $one['limit'] = 0;
            $viewRecord = $this->isShortLoveOrCollect($uid, $one['id']);
            $one['is_love'] = $viewRecord['is_love'] ?? 0;
            //是否收藏
            $one['is_collect'] = $viewRecord['is_collect'] ?? 0;
            $one['url'] = env('RESOURCE_DOMAIN') . '/' . $one['url'];
            // $one['cover_img'] = env('RESOURCE_DOMAIN') . '/' . $one['cover_img'];
            $dSeconds = intval($one['duration_seconds'] ?: 1);
            $one['start_second'] = $dSeconds - ($dSeconds - (time() % $dSeconds));


            $domainSync = VideoTrait::getDomain($one['sync']);
            $one['cover_img'] = $domainSync . $one['cover_img'];
            $one['gold'] = $one['gold'] / $this->goldUnit;
            $one['views'] = $one['views'] > 0 ? $this->generateRandViews($one['views']) : $this->generateRandViews(rand(5, 9));
            $one['hls_url'] = $domainSync . $one['hls_url'];
            $one['preview_hls_url'] = $this->getPreviewPlayUrl($one['hls_url']);
            $one['dash_url'] = $domainSync . $one['dash_url'];
            $one['preview_dash_url'] = $this->getPreviewPlayUrl($one['dash_url'], 'dash');
            $data[] = $one;
        }
        return [
            'list' => $data,
            'hasMorePages' => $paginator->hasMorePages()
        ];
    }

    /**
     * 播放
     * @param Request $request
     * @return JsonResponse
     */
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
            $res = $this->items($page, $uid, $starId,$cateId,$tagId);
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
     * 直播时长统计接口
     * @param Request $request
     * @return JsonResponse
     */
    public function calc(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $uid = $user->id;
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'duration_seconds' => 'nullable',
                'time' => 'nullable',
            ])->validated();
            $durationSeconds = $params['duration_seconds'] ?? 0;
            $time = $params['time'] ?? 0;
            $redisLiveCalcKey = sprintf("live_calc_%s",$uid);
            $redis = $this->redis();
            $usedTime = $redis->get($redisLiveCalcKey)?:0;
            $nowTime = $usedTime + $time;
            $mint3 = 3 * 60;
            $remainSecond = $mint3 - $nowTime;
            if ($remainSecond < 0) {
                $remainSecond = 0;
            }
            $redis->set($redisLiveCalcKey,$nowTime);

            $startSecond = $durationSeconds - ($durationSeconds - (time() % $durationSeconds));

            return response()->json([
                'state' => 0,
                'data' => [
                    'is_vip' => $user->vip>0 ? 1 : 0,
                    'start_second' => $startSecond,
                    'remain_second' => $remainSecond
                ]
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("liveCalc", [$msg]);
            return response()->json([
                'state' => -1,
                'data' => $msg
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
        $one['is_love'] = $redis->get("live_is_love_{$uid}_{$vid}") ?: 0;
        //是否收藏
        $one['is_collect'] = $redis->get("live_is_collect_{$uid}_{$vid}") ?: 0;
        return $one;
    }

}