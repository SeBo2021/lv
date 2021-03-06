<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\AdTrait;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\BbsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommHomeController extends Controller
{
    use PHPRedisTrait;
    use BbsTrait;

    /**
     * @throws ValidationException
     */
    public function info(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params, [
                'id' => 'required|integer',
                'page' => 'required|integer',
            ])->validate();
            $id = $validated['id'];
        } else {
            return [];
        }
        $page = $params['page'] ?? 1;
        $raw = $this->redis()->hGet("comm_home_cache_{$id}", $page);
        if ($raw) {
            $res = json_decode($raw,true);
        } else {
            //二级分类列表
            $perPage = 6;
            $paginator = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel','video_picture')
                ->where('community_bbs.author_id', $id)->orderBy('updated_at', 'desc')
                ->orderBy('updated_at')
                ->simplePaginate($perPage, ['*'], '', $page);
            $secondCateList = $paginator->toArray();
            $data = $secondCateList['data'];
            $user = $request->user();
            $uid = $user->id;
            $result = $this->proProcessData($uid, $data, $user);
            //加入视频列表
            $res['hasMorePages'] = $paginator->hasMorePages();
            $userInfo = User::query()
                ->select('id','nickname','is_office','location_name','attention','fans','avatar','loves','sex')
                ->find($id);
            $uid = $request->user()->id;
            if (CommFocus::query()->where(['user_id'=>$uid,'to_user_id'=>$userInfo->id])->exists()) {
                $userInfo->is_focus = 1;
            } else {
                $userInfo->is_focus = 0;
            }
            $res['user_info'] = $userInfo;
            $res['bbs_list'] = $result;
            $this->redis()->hSet("comm_home_cache_{$id}", $page,json_encode($res));
        }

        return response()->json([
            'state' => 0,
            'data' => $res
        ]);
    }

}