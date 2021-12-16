<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommComments;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommCommentController extends Controller
{
    public function post(Request $request)
    {
        if(isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
                'content' => 'required',
            ])->validate();
            $vid = $params['bbs_id'];
            $content = $params['content'];
            $insertData = [
                'bbs_id' => $vid,
                'user_id' => $request->user()->id,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $commentId = DB::table('community_comments')->insertGetId($insertData);
                CommBbs::where('id',$vid)->increment('comments');
                DB::commit();
                if($commentId >0){
                    return response()->json([
                        'state'=>0,
                        'msg'=>'评论成功'
                    ]);
                }
            }catch (\Exception $e){
                DB::rollBack();
                Log::error('bbsComments===' . $e->getMessage());
            }
            return response()->json([
                'state'=>-1,
                'msg'=>'评论失败'
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
        if(isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'bbs_id' => 'required|integer',
            ])->validate();
            $bbsId = $params['bbs_id'] ?? 1;
            $page = $params['page'] ?? 1;
            $perPage = 16;
            $queryBuild = CommComments::query()
                ->leftJoin('users', 'community_comments.user_id', '=', 'users.id')
                ->select('community_comments.id','content','users.id as uid','users.nickname','community_comments.created_at as reply_at','users.avatar')
                ->where('bbs_id',$bbsId);
            $paginator = $queryBuild->orderBy('id','desc')->simplePaginate($perPage,'*','commentLists',$page);
            $items = $paginator->items();
            $res['list'] = $items;
            $res['hasMorePages'] = $paginator->hasMorePages();
            return response()->json([
                'state'=>0,
                'data'=>$res
            ]);
        }
        return [];
    }
}