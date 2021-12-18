<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommChat;
use App\Models\CommComments;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommChatController extends Controller
{
    use PHPRedisTrait;
    public function post(Request $request)
    {

        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                'type' => 'nullable',
                'content' => 'required',
            ])->validate();
            $vid = $params['to_user_id'];
            $type = $params['type']??1;
            $content = $params['content'];
            $uid = $request->user()->id;
            $insertData = [
                'to_user_id' => $vid,
                'user_id' => $uid,
                'type' => $type,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $commentId = DB::table('community_chat')->insertGetId($insertData);
                DB::commit();
                // 创建关系
                $relationName = "relation_chat";
                // 处理缓存结构
                $min = min($vid,$uid);
                $max = max($uid,$vid);
                $existKey = "chat_pair_{$min}_{$max}";
                $exitPair = $this->redis()->get($existKey);
                if ($exitPair) {
                    $this->redis()->sRem($relationName,$exitPair);
                } else {
                    $this->redis()->set($existKey,$commentId);
                }
                $this->redis()->sAdd($relationName,$commentId);

                if ($commentId > 0) {
                    return response()->json([
                        'state' => 0,
                        'msg' => '发送成功'
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('bbsChat===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => '发送失败'
            ]);

        }
        return [];
    }

    /**
     * 评论列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'nullable',
                'start_id' => 'nullable',
                'start_time' => 'nullable',
                'page' => 'required|integer',
            ])->validate();
            $toUserId = $params['to_user_id'] ?? 0;
            $startId = $params['start_id'] ?? 0;
            $startTime = $params['start_time'] ?? 0;
            $page = $params['page'] ?? 1;
            $perPage = 8;
            $queryBuild = CommChat::query()
                ->leftJoin('users', 'community_chat.user_id', '=', 'users.id')
                ->select('community_chat.id','user_id','to_user_id','content','community_chat.created_at','users.nickname as to_user_nickname','users.avatar', 'community_chat.type');
            if ($startId) {
                $queryBuild->where('id', '>', $startId);
            } else {
                $subIds = CommChat::query()->select(DB::raw('max(id) as max_id, to_user_id'))->groupBy('to_user_id')->pluck('max_id');
                $queryBuild->whereIn('community_chat.id', $subIds);
            }
            $uid = $request->user()->id;
            if ($toUserId) {
                $queryBuild->where(function($sql) use ($uid,$toUserId){
                    $sql->whereIn('user_id',[$uid,$toUserId]);
                    $sql->whereIn('to_user_id',[$uid,$toUserId]);
                });
            }
            if ($startTime) {
                $queryBuild->where('created_at', '>=', $startTime);
            }
            $paginator = $queryBuild->orderBy('id')->simplePaginate($perPage, '*', 'commentLists', $page);
            $items = $paginator->items();
            $res['list'] = $items;
            $res['hasMorePages'] = $paginator->hasMorePages();
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return [];
    }
}