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
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
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
    use StatisticTrait;
    use MemberCardTrait;

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
        if ($page == 1) {
            $ids = $this->redis()->get('fakeLiveIds')?:'';
            $redisIds = explode(',',$ids);
            shuffle($redisIds);
            $newIds = implode(',',$redisIds);
            $this->redis()->set("newLiveByUid_{$uid}",$newIds);
        } else {
            $newIds = $this->redis()->get("newLiveByUid_{$uid}");
        }
        $perPage = 8;
        $cacheIds = explode(',',$newIds);
        if (!$newIds) {
            return [];
        }
        $start = $perPage*($page-1);
        if ($start < 0) {
            $start = 1;
        }
        $ids = array_slice($cacheIds,$start,$perPage);
        foreach ($ids as $id) {
            $mapNum = $id % 100;
            $cacheKey = "fake_live_$mapNum";
            $raw = $this->redis()->hGet($cacheKey, $id);
            if ($raw) {
                $items[] = json_decode($raw,true);
            }
        }
        $more = false;
        if (count($ids) == $perPage) {
            $more = true;
        }

        $data = [];
        $_v = date('Ymd');
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
            //$one['cover_img'] = $domainSync . $one['cover_img'];
            $fileInfo = pathinfo($one['cover_img']);
            $one['cover_img'] = $domainSync . $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm?ext=jpg&_v='.$_v;

            $one['gold'] = $one['gold'] / $this->goldUnit;
            $one['views'] = $one['views'] > 0 ? $this->generateRandViews($one['views']) : $this->generateRandViews(rand(5, 9));
            $one['hls_url'] = $domainSync . $one['hls_url'];
            $hlsInfo = pathinfo($one['hls_url']);
            $one['hls_url'] = $hlsInfo['dirname'].'/'.$hlsInfo['filename'].'_0_1000.vid?id='.$one['id'].'&_v='.$_v;
            $one['preview_hls_url'] = $this->getPreviewPlayUrl($one['hls_url']);
            $previewHlsInfo = pathinfo($one['preview_hls_url']);
            $one['preview_hls_url'] = $previewHlsInfo['dirname'].'/'.$previewHlsInfo['filename'].'.vid?id='.$one['id'].'&_v='.$_v;
            $one['dash_url'] = $domainSync . $one['dash_url'];
            $one['preview_dash_url'] = $this->getPreviewPlayUrl($one['dash_url'], 'dash');
            $data[] = $one;
        }
        return [
            'list' => $data,
            'hasMorePages' => $more
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
            Log::error("liveLists", [$msg]);
            return response()->json([
                'state' => -1,
                'data' => $msg
            ]);
        }
    }

    public static function transferSeconds($str)
    {
        $His = explode(':',$str);
        $seconds = 0;
        if(!empty($His)){
            switch (array_key_last($His)){
                case 0:
                    $His[0]+=0;
                    $seconds = $His[0];
                    break;
                case 1:
                    $His[0]+=0;
                    $His[1]+=0;
                    $seconds = $His[0]*60 + $His[1];
                    break;
                case 2:
                    $His[0]+=0;
                    $His[1]+=0;
                    $His[2]+=0;
                    $seconds = $His[0] * 60 * 60 + $His[1] * 60 + $His[2];
                    break;
            }
        }
        return $seconds;
    }

    /**
     * 直播时长统计接口
     * @param Request $request
     * @return JsonResponse
     */
    public function calc(Request $request): JsonResponse
    {
        $user = $request->user();
        $uid = $user->id;
        $params = ApiParamsTrait::parse($request->params);
        Validator::make($params, [
            'duration_seconds' => 'nullable',
            'time' => 'nullable',
        ])->validated();
        //Log::info('==LiveCalcParams==',[$params]);
        $durationSeconds = $params['duration_seconds'] ?? 0;
        if(!is_int($durationSeconds)){
            $durationSeconds = self::transferSeconds($durationSeconds);
        }
        //Log::info('==LiveCalcParams==',[$params,$durationSeconds]);
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
        $exp = strtotime('23:59:59')-time();
        $redis->set($redisLiveCalcKey,$nowTime,$exp);

        $startSecond = $durationSeconds - ($durationSeconds - (time() % $durationSeconds));

        $isVip = (int)$this->isVip($user);
        /*$isVip = 1;
        if(!$user->member_card_type || (time() - $user->vip_expired > $user->vip_start_last)){
            $isVip = 0;
        }*/
        //$isVip = $user->vip>0 ? 1 : 0;
        /*if($user->long_vedio_times>0){ //有次数也视为VIP
            $isVip = 1;
        }*/
        //统计激活视频人数=========
        $this->saveUsersDay($uid, $user->channel_id, $user->device_system);
        //============================
        return response()->json([
            'state' => 0,
            'data' => [
                'is_vip' => $isVip,
                'start_second' => $startSecond,
                'remain_second' => $remainSecond
            ]
        ]);
        /*try {

        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("liveCalc", [$msg]);
            return response()->json([
                'state' => -1,
                'data' => $msg
            ]);
        }*/
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