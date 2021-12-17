<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommChat;
use App\Models\CommComments;
use App\Models\CommMessage;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommMessageController extends Controller
{
    use PHPRedisTrait;
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
            $queryBuild = CommChat::query()
                ->leftJoin('users', 'community_chat.user_id', '=', 'users.id')
                ->select('community_chat.id', 'user_id', 'to_user_id', 'content', 'community_chat.created_at', 'users.nickname as to_user_nickname', 'users.avatar');
            $relationName = "relation_chat";
            $subIds = $this->redis()->sMembers($relationName);
            $subIds = array_merge($subIds,[17]);
            $queryBuild->whereIn('community_chat.id', $subIds);

            $paginator = $queryBuild->orderBy('id')->simplePaginate($perPage, '*', 'commentLists', $page);
            $items = $paginator->items();

            $userIds = [];
            foreach ($items as $k=>$item) {
                $userIds[] = $item['user_id'];
                $userIds[] = $item['to_user_id'];
                $items[$k]['no_read'] = 1;
            }
            $userData = User::query()->whereIn('id',$userIds)->get()->toArray();
            $userInfo = array_column($userData,null,'id');

            $uid = $request->user()->id;
            foreach ($items as $k=>$item) {
                if ($item['user_id'] == $uid) {
                    $items[$k]['avatar'] = $userInfo[$item['to_user_id']]['avatar'];
                    $items[$k]['to_user_nickname'] =  $userInfo[$item['to_user_id']]['nickname'];
                }
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