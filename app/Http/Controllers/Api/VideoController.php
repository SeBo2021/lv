<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\ProcessViewVideo;
use App\Models\Domain;
use App\Models\GoldLog;
use App\Models\User;
use App\Models\Video;
use App\Models\ViewRecord;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\MemberCardTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use App\TraitClass\VideoTrait;
use App\TraitClass\VipRights;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    use VideoTrait;
    use PHPRedisTrait;
    use VipRights;
    use MemberCardTrait;
    use StatisticTrait;

    //播放

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function actionView(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $viewLongVideoTimes = $user->long_vedio_times; //观看次数
        //todo 是否会员的逻辑处理，暂时每天免费三次
        if (!($request->params ?? false)) {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        // 业务逻辑
        $params = ApiParamsTrait::parse($request->params);
        $validated = Validator::make($params, [
            'id' => 'required|integer|min:1',
            'use_gold' => [
                'nullable',
                'string',
                Rule::in(['1', '0']),
            ],
        ])->validated();
        // 增加冗错机制
        $id = $validated['id']??false;
        if (!$id) {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        $useGold = $validated['use_gold'] ?? "1";
        /*$videoField = ['id', 'name', 'cid', 'cat', 'restricted', 'sync', 'title', 'url', 'gold', 'duration', 'hls_url', 'dash_url', 'type', 'cover_img', 'views', 'likes', 'comments','updated_at'];
        $one = Video::query()->find($id, $videoField)->toArray();*/
        $one = (array)$this->getVideoById($id);
        if (!empty($one)) {
            $one = $this->handleVideoItems([$one], true,$user->id)[0];
            $one['limit'] = 0;
            //
            if($user->long_vedio_times>0){//统计激活
                $configData = config_cache('app');
                $setTimes = $configData['free_view_long_video_times'] ?? 0;
                if(($user->long_vedio_times==$setTimes) && (date('Y-m-d')==date('Y-m-d',strtotime($user->created_at)))){
                    $this->saveStatisticByDay('active_view_users',$user->channel_id,$user->device_system);
                }
                //
                DB::table('users')->where('id',$user->id)->decrement('long_vedio_times'); //当日观看次数减一
            }
            ProcessViewVideo::dispatchAfterResponse($user, $one);
            /*$job = new ProcessViewVideo($user, $one);
            $this->dispatchSync($job);*/

            //观看限制
            if ($one['restricted'] != 0) {
                //是否有观看次数
                if ($viewLongVideoTimes <= 0) {
                    $one['restricted'] += 0;
                    /*if ($user->phone_number > 0) {*/
                    // unset($one['preview_hls_url'], $one['preview_dash_url']);
                    $one = $this->vipOrGold($one, $user);
                    if ($useGold && $one['limit'] == 2) {
                        // 如果金币则尝试购买
                        $buy = $this->useGold($one, $user);
                        $buy && ($one['limit'] = 0);
                    }
                    return response()->json([
                        'state' => 0,
                        'data' => $one
                    ]);

                }
            }
        }
        Cache::forget("cachedUser.{$user->id}");
        //Log::info('==Limit==',[$one]);
        return response()->json([
            'state' => 0,
            'data' => $one
        ]);
        /*try {

        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionView", [$msg]);
        }
        return 0;*/
    }

    //点赞
    public function actionLike(Request $request)
    {
        if (isset($request->params)) {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'like' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['like'];
            try {
                if ($is_love) {
                    Video::query()->where('id', $id)->increment('likes');
                } else {
                    Video::query()->where('id', $id)->decrement('likes');
                }
                $attributes = ['uid' => $user->id, 'vid' => $id];
                $values = ['is_love' => $is_love];
                ViewRecord::query()->updateOrInsert($attributes, $values);
                return response()->json([
                    'state' => 0,
                    'data' => [],
                ]);
            } catch (Exception $exception) {
                $msg = $exception->getMessage();
                Log::error("actionLike", [$msg]);
            }
        } else {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        return 0;
    }

    public function actionShare(Request $request)
    {
        $user = $request->user();
        $code = $user->promotion_code ?? null;
        if (!empty($code)) {
            $domainArr = Domain::query()
                ->where('status', 1)
                ->where('type', '<', 2)
                ->get(['id', 'name'])->toArray();
            $randKey = array_rand($domainArr);
            $domain = $domainArr[$randKey]['name'];
            $promotion_url = $domain . '?code=' . $code;
            //奖励规则
            $appConfig = config_cache('app');
            return response()->json([
                'state' => 0,
                'data' => [
                    'invite_code' => $code,
                    'reward_rules' => $appConfig['reward_rules'] ?? '',
                    'promotion_url' => $promotion_url
                ],
            ]);
        }
        return [];
    }

    //
    public function actionCollect(Request $request)
    {
        if (isset($request->params)) {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'collect' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_collect = $params['collect'];

            if(!$this->commentRight($user)){
                return response()->json([
                    'state' => -2,
                    'msg' => "权限不足",
                ]);
            }
            try {
                Video::query()->where('id', $id)->increment('likes');
                $attributes = ['uid' => $user->id, 'vid' => $id];
                $values = ['is_collect' => $is_collect,'time_at'=>time()];
                ViewRecord::query()->updateOrInsert($attributes, $values);
                return response()->json([
                    'state' => 0,
                    'data' => [],
                ]);
            } catch (Exception $exception) {
                $msg = $exception->getMessage();
                Log::error("actionLike", [$msg]);
            }
        } else {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        return [];
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
                if(!$this->isVip($user)){
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

    /**
     * 花费金豆
     * @param $one
     * @param $user
     * @return bool
     */
    public function useGold($one, $user): bool
    {
        // 扣除金币
        $redisHashKey = $this->apiRedisKey['user_gold_video'] . $user->id;
        $now = date('Y-m-d H:i:s', time());
        $newGold = $user->gold - $one['gold'];
        $model = User::query();
        $userEffect = $model->where('id', '=', $user->id)
            ->where('gold', '>=', $one['gold'])
            ->update(
                ['gold' => $newGold]
            );
        if (!$userEffect) {
            return false;
        }
        $logEffect = GoldLog::query()->create([
            'uid' => $user->id,
            'goods_id' => $one['id'],
            'cash' => $one['gold'],
            'goods_info' => json_encode($one),
            'before_cash' => $user->gold,
            'use_type' => 1,
            'device_system' => $user->device_system,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if (!$logEffect) {
            return false;
        }
        $this->redis()->sAdd($redisHashKey, $one['id']);
        return true;
    }
}