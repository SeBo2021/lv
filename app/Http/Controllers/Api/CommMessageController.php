<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommChat;
use App\Models\CommComments;
use App\Models\CommMessage;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommMessageController extends Controller
{
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
                'page' => 'required|integer',
            ])->validate();
            $page = $params['page'] ?? 1;
            $perPage = 8;
            $queryBuild = CommMessage::query()
                ->leftJoin('users', 'community_message.to_user_id', '=', 'users.id')
                ->select('community_message.id', 'user_id', 'to_user_id', 'content', 'community_message.created_at', 'users.nickname as to_user_nickname', 'users.avatar');

            $subIds = CommMessage::query()->select(DB::raw('max(id) as max_id, to_user_id'))->groupBy('to_user_id')->pluck('max_id');
            $queryBuild->whereIn('community_message.id', $subIds);

            $paginator = $queryBuild->orderBy('id')->simplePaginate($perPage, '*', 'commentLists', $page);
            $items = $paginator->items();
            foreach ($items as $k=>$item) {
                $items[$k]['no_read'] = 1;
            }
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