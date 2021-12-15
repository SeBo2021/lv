<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\User;
use App\Models\Video;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CommContentController extends Controller
{
    use PHPRedisTrait;

    public function post(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'content' => 'required',
                'thumbs' => 'nullable',
                'video' => 'nullable',
                'category_id' => 'nullable',
                'location_name' => 'nullable',
            ])->validate();
            $content = $params['content'] ?? '';
            $thumbs = $params['thumbs'] ?? '[]';
            $video = $params['video'] ?? '[]';
            $categoryId = $params['category_id'] ?? '';
            $locationName = $params['location_name'] ?? '';
            $insertData = [
                'thumbs' => $thumbs,
                'video' => $video,
                'category_id' => $categoryId,
                'location_name' => $locationName,
                'author_id' => $request->user()->id,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            try {   //先偿试队列
                $commentId = DB::table('community_bbs')->insertGetId($insertData);
                DB::commit();
                if ($commentId > 0) {
                    return response()->json([
                        'state' => 0,
                        'msg' => '发帖成功'
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('bbsPost===' . $e->getMessage());
            }
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);

        }
        return [];
    }

    private function updateStatue($uid, $mark)
    {
        $redis = $this->redis();
        $keyCate = "status_cate_{$mark}";
        $time = time();
        $redis->set($keyCate, $time);

        // 更新关注
        if ($mark == 'focus') {

        }
    }

    public function lists(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'cid_1' => 'nullable',
                'cid_2' => 'nullable',
            ])->validate();
            // 一二级分类
            $cid1 = $params['cid_1'] ?? 0;
            $cid2 = $params['cid_2'] ?? 0;
            // 得到一级分类help
            $help = $this->redis()->hGet('common_cate_help', "c_{$cid1}");
            $uid = $request->user()->id;
            if (in_array($help, ['focus'])) {
                $res = $this->$help($uid);
            } else {
                $res = $this->other($request->user()->id, $cid1, $cid2);
            }
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        }
        return [];

    }


    /**
     * 详情
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function detail(Request $request): JsonResponse|array
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'id' => 'integer',
            ])->validate();
            $id = $params['id'] ?? 0;
            $data = CommBbs::query()
                ->leftJoin('users', 'community_bbs.id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video')
                ->where('community_bbs.id', $id)->orderBy('updated_at', 'desc')->get();
            return response()->json([
                'state' => 0,
                'data' => $data
            ]);
        }
        return [];
    }

    /**
     * 关注列表
     * @param $uid
     * @return Builder[]|Collection
     */
    private function focus($uid, $perPage = 6, $page = 1)
    {

        $userList = CommFocus::where('user_id', $uid)->pluck('to_user_id');
        $paginator = CommBbs::query()
            ->leftJoin('users', 'community_bbs.id', '=', 'users.id')
            ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
            ->whereIn('author_id', $userList)->orderBy('updated_at', 'desc')
            ->simplePaginate($perPage, ['*'], '', $page);
        //加入视频列表
        $res['hasMorePages'] = $paginator->hasMorePages();
        $list = $paginator->items() ?? [];
        $result = $this->proProcessData($uid, $list);
        $data['bbs_list'] = $result;
        return $res;
    }

    /**
     * 其它类别
     * @param $uid
     * @param int $cid1
     * @param int $cid2
     * @return array|Builder[]|Collection
     */
    private function other($uid, $cid1 = 0, $cid2 = 0, $perPage = 6, $page = 1): Collection|array
    {
        if ($cid2) {
            $paginator = CommBbs::query()
                ->leftJoin('users', 'community_bbs.id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
                ->where('category_id', $cid2)->orderBy('updated_at', 'desc')
                ->simplePaginate($perPage, ['*'], '', $page);
            $data['hasMorePages'] = $paginator->hasMorePages();
            $list = $paginator->items();
            $result = $this->proProcessData($uid, $list);
            $data['bbs_list'] = $result;
            return $data;
        }
        if ($cid1) {
            $ids = $this->getChild($cid1, false);

            $paginator = CommBbs::query()
                ->leftJoin('users', 'community_bbs.id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
                ->whereIn('category_id', $ids)
                ->orderBy('updated_at', 'desc')
                ->simplePaginate($perPage, ['*'], '', $page);
            $data['hasMorePages'] = $paginator->hasMorePages();
            $list = $paginator->items();

            $result = $this->proProcessData($uid, $list);
            $data['bbs_list'] = $result;

            return $data;
        }

        return [];
    }

    /**
     * @param $uid
     * @param $list
     * @return mixed
     */
    private function proProcessData($uid, $list): mixed
    {
        foreach ($list as $k => $re) {
            if ($this->redis()->get("focus_{$uid}_{$re['uid']}") == 1) {
                $list[$k]['is_focus'] = 1;
            } else {
                $list[$k]['is_focus'] = 0;
            }
            if ($re['video']) {
                $list[$k]['video_picture'] = [];
            } else {
                $list[$k]['video_picture'] = [];
            }
            if ($this->redis()->get("comm_like_{$uid}_{$re['id']}") == 1) {
                $list[$k]['is_love'] = 1;
            } else {
                $list[$k]['is_love'] = 0;
            }
            $list[$k]['thumbs']  = json_decode($re['thumbs'],true);
        }
        return $list;
    }

    /**
     * 得到子分类
     * @param $id
     * @param bool $raw
     * @return mixed
     */
    private function getChild($id, $raw = true): mixed
    {
        $data = [];
        $tree = json_decode($this->redis()->get('common_cate'), true) ?? [];
        foreach ($tree as $item) {
            if ($item['id'] == $id) {
                $data = $item['child'];
            }
        }
        if ($raw) {
            return $data;
        }
        return array_column($data, 'id');

    }
}