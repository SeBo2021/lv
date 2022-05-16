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
    use VideoTrait,PHPRedisTrait,VipRights,MemberCardTrait,StatisticTrait,ApiParamsTrait;

    //播放
    public function actionView(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (isset($request->params)) {
                $user = $request->user();
                $viewLongVideoTimes = $user->long_vedio_times; //观看次数
                // 业务逻辑
                $params = self::parse($request->params);
                $validated = Validator::make($params, [
                    'id' => 'required|integer|min:1',
                    'use_gold' => [
                        'nullable',
                        'string',
                        Rule::in(['1', '0']),
                    ],
                ])->validated();
                // 增加冗错机制
                $id = $validated['id']??0;
                if ($id==0) {
                    return response()->json(['state' => -1, 'msg' => "参数错误",'data'=>[]]);
                }
                $useGold = $validated['use_gold'] ?? "1";
                $one = (array)$this->getVideoById($id);
                if (!empty($one)) {
                    $one = $this->handleVideoItems([$one], true,$user->id)[0];
                    $one['limit'] = 0;
                    //
                    ProcessViewVideo::dispatchAfterResponse($user, $one);
                    //观看限制
                    if ($one['restricted'] != 0) {
                        //是否有观看次数
                        $one['restricted'] += 0;
                        if (($viewLongVideoTimes <= 0) || ($one['restricted']!=1)) {
                            /*if ($user->phone_number > 0) {*/
                            // unset($one['preview_hls_url'], $one['preview_dash_url']);
                            $one = $this->vipOrGold($one, $user);
                            if ($useGold && $one['limit'] == 2) {
                                // 如果金币则尝试购买
                                $buy = $this->useGold($one, $user);
                                $buy && ($one['limit'] = 0);
                            }
                        }
                    }
                }
                Cache::forget('cachedUser.'.$user->id);
                return response()->json(['state' => 0, 'data' => $one]);
            }
            return response()->json(['state' => -1, 'msg' => "参数错误",'data'=>[]]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionView", [$msg]);
            return response()->json(['state' => -1, 'msg' => $msg,'data'=>[]]);
        }

    }

    //点赞
    public function actionLike(Request $request)
    {
        if (isset($request->params)) {
            $user = $request->user();
            $params = self::parse($request->params);
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
            $params = self::parse($request->params);
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
                Video::query()->where('id', $id)->increment('collects');
                $attributes = ['uid' => $user->id, 'vid' => $id];
                $values = ['is_collect' => $is_collect,'time_at'=>time()];
                ViewRecord::query()->updateOrInsert($attributes, $values);

            } catch (Exception $exception) {
                $msg = $exception->getMessage();
                Log::error("actionCollect", [$msg]);
            }
        } else {
            return response()->json([
                'state' => -1,
                'msg' => "参数错误",
            ]);
        }
        return response()->json([
            'state' => 0,
            'data' => [],
        ]);
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