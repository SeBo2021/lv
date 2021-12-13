<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommChat;
use App\Models\CommComments;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommChatController extends Controller
{
    public function post(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                'content' => 'required',
            ])->validate();
            $vid = $params['to_user_id'];
            $content = $params['content'];
            $insertData = [
                'to_user_id' => $vid,
                'user_id' => $request->user()->id,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $commentId = DB::table('community_chat')->insertGetId($insertData);
                DB::commit();
                if ($commentId > 0) {
                    return response()->json([
                        'state' => 0,
                        'msg' => '发送成功'
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('bbsComments===' . $e->getMessage());
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
                'to_user_id' => 'required|string',
                'start_id' => 'string',
                'start_time' => 'string',
                'page' => 'required|integer',
            ])->validate();
            $toUserId = $params['to_user_id'] ?? 0;
            $startId = $params['start_id'] ?? 0;
            $startTime = $params['start_time'] ?? 0;
            $page = $params['page'] ?? 1;
            $perPage = 8;
            $queryBuild = CommChat::with('user:id,nickname')
                ->select('id','user_id','to_user_id','content','created_at')
                ->where('to_user_id', $toUserId);
            if ($startId) {
                $queryBuild->where('id', '>', $startId);
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