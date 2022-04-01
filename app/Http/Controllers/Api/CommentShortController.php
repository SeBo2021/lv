<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoShort;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\VipRights;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommentShortController extends Controller
{
    use VipRights;
    /**
     * 视频评论
     * @param Request $request
     * @return JsonResponse
     */
    public function submit(Request $request): JsonResponse
    {
        try {
            //权限控制
            $user = $request->user();
            if(!$this->commentRight($user)){
                return response()->json([
                    'state' => -2,
                    'msg' => "权限不足",
                ]);
            }
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'vid' => 'required|integer',
                'content' => 'required',
            ])->validate();
            $vid = $params['vid'];
            $content = $params['content'];
            $insertData = [
                'vid' => $vid,
                'uid' => $user->id,
                'content' => $content,
                'reply_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            //先偿试队列
            DB::table('comments_short')->insertGetId($insertData);
            VideoShort::where('id', $vid)->increment('comments');
            DB::commit();
            return response()->json([
                'state' => 0,
                'msg' => '评论成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CommentShortSubmit===' . $e->getMessage());
            return response()->json([
                'state' => -1,
                'msg' => '评论失败'
            ]);
        }
    }

    /**
     * 评论回复
     * @param Request $request
     * @return JsonResponse
     */
    public function reply(Request $request): JsonResponse
    {
        //权限控制
        $user = $request->user();
        if(!$this->commentRight($user)){
            return response()->json([
                'state' => -2,
                'msg' => "权限不足",
            ]);
        }
        try {
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params, [
                'comment_id' => 'required|integer|min:1',
                'vid' => 'required|integer|min:1',
                'content' => 'required',
            ])->validated();
            $replied_uid = DB::table('comments_short')->where('id', $validated['comment_id'])->value('uid');
            $comment = DB::table('comments_short')->find($validated['comment_id'], ['reply_cid']);
            if ($comment->reply_cid > 0) {
                $validated['comment_id'] = DB::table('comments_short')->where('id', $validated['comment_id'])->value('reply_cid');
            }
            $insertData = [
                'reply_cid' => $validated['comment_id'],
                'vid' => $validated['vid'],
                'uid' => $request->user()->id,
                'replied_uid' => $replied_uid,
                'content' => $validated['content'],
                'reply_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            DB::table('comments_short')->insert($insertData);
            DB::table('comments_short')->where('id', $validated['comment_id'])->increment('replies');
            DB::commit();
            return response()->json([
                'state' => 0,
                'msg' => '回复成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CommentShortReply===' . $e->getMessage());
            return response()->json([
                'state' => -1,
                'msg' => '回复失败'
            ]);
        }
    }

    /**
     * 评论列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request): JsonResponse|array
    {
        try {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'vid' => 'required|integer',
            ])->validate();
            $reply_cid = $params['comment_id'] ?? 0;
            $vid = $params['vid'];
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $fields = ['comments_short.id', 'vid', 'uid', 'reply_cid', 'replied_uid', 'content', 'replies', 'reply_at', 'users.avatar', 'users.nickname'];
            $queryBuild = DB::table('comments_short')
                ->join('users', 'comments_short.uid', '=', 'users.id')
                ->where('comments_short.status', 1)
                ->where('comments_short.vid', $vid);
            $queryBuild = $queryBuild->where('reply_cid', $reply_cid);
            $paginator = $queryBuild->orderBy('id','desc')->simplePaginate($perPage, $fields, 'commentLists', $page);
            $items = $paginator->items();
            $res['list'] = $items;
            $res['hasMorePages'] = $paginator->hasMorePages();

            $replied_uid = [];
            foreach ($res['list'] as &$item) {
                if ($item->replied_uid > 0) {
                    $replied_uid[] = $item->replied_uid;
                }
            }
            $repliedUser = DB::table('users')->whereIn('id', $replied_uid)->pluck('nickname', 'id')->all();
            foreach ($res['list'] as &$item) {
                if ($item->replied_uid > 0) {
                    $item->replied_nickname = $repliedUser[$item->replied_uid];
                }
            }
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);

        } catch (\Exception $e) {
            Log::error('CommentShortLists===' . $e->getMessage());
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);
        }
    }
}