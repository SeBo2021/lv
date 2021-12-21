<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommOperationController extends Controller
{
    use PHPRedisTrait;

    /**
     * 关注
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function foucs(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'to_user_id' => 'required|integer',
                "focus" => 'nullable' //类型 :1-关注,0-取消收藏
            ])->validate();
            $toUserId = $params['to_user_id'];
            $date = date('Y-m-d H:i:s', time());
            $insertData = [
                'user_id' => $request->user()->id,
                'to_user_id' => $toUserId,
                'created_at' => $date,
                'updated_at' => $date,

            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $focus = $params['focus'] ?? 1;
                if ($focus == 0) {
                    DB::table('community_focus')->where($insertData)->delete();
                    User::where('id', $toUserId)->where('fans', '>', 0)->decrement('fans');
                } else {
                    DB::table('community_focus')->insert($insertData);
                    User::where('id', $toUserId)->increment('fans');
                }
                DB::commit();
                return response()->json([
                    'state' => 0,
                    'msg' => '操作成功'
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('operationFocusUser===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);

        }
        return [];
    }


    /**
     * 点赞
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function like(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
                'like' => 'required|integer'
            ])->validate();
            $bbsId = $params['bbs_id'];
            $uid = $request->user()->id;
            $insertData = [
                'user_id' => $uid,
                'bbs_id' => $bbsId,
            ];
            $is_love = $params['like'];
            DB::beginTransaction();
            try {   //先偿试队列
                if ($is_love) {
                    if (DB::table('community_like')->where($insertData)->exists()) {
                        return response()->json([
                            'state' => -2,
                            'msg' => '已经操作'
                        ]);
                    }
                    DB::table('community_like')->insert($insertData);
                    CommBbs::where('id', $bbsId)->increment('likes');
                    $this->redis()->set("comm_like_{$uid}_{$bbsId}", 1);
                    DB::commit();
                } else {
                    DB::table('community_like')->where($insertData)->delete();
                    CommBbs::where('id', $bbsId)->where('likes', '>', 0)->decrement('likes');
                    $this->redis()->del("comm_like_{$uid}_{$bbsId}");
                    DB::commit();
                }

                return response()->json([
                    'state' => 0,
                    'msg' => '操作成功'
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('operationLikeBbs===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);

        }
        return [];
    }

    public function reply(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params, [
                'comment_id' => 'required|integer|min:1',
                'vid' => 'required|integer|min:1',
                'content' => 'required',
            ])->validated();

            $replied_uid = DB::table('comments')->where('id', $validated['comment_id'])->value('uid');
            $comment = DB::table('comments')->find($validated['comment_id'], ['reply_cid']);
            if ($comment->reply_cid > 0) {
                $validated['comment_id'] = DB::table('comments')->where('id', $validated['comment_id'])->value('reply_cid');
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
            DB::table('comments')->insert($insertData);
            DB::table('comments')->where('id', $validated['comment_id'])->increment('replies');
            DB::commit();
            return response()->json([
                'state' => 0,
                'msg' => '回复成功'
            ]);
        }
        return [];
    }

    public function lists(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'vid' => 'required|integer',
            ])->validate();
            $reply_cid = $params['comment_id'] ?? 0;
            $vid = $params['vid'];
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $fields = ['comments.id', 'vid', 'uid', 'reply_cid', 'replied_uid', 'content', 'replies', 'reply_at', 'users.avatar', 'users.nickname'];
            $queryBuild = DB::table('comments')
                ->join('users', 'comments.uid', '=', 'users.id')
                ->where('comments.vid', $vid);
            $queryBuild = $queryBuild->where('reply_cid', $reply_cid);
            $paginator = $queryBuild->orderBy('id')->simplePaginate($perPage, $fields, 'commentLists', $page);
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
        }
        return [];
    }
}