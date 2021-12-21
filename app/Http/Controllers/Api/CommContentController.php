<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\User;
use App\Models\Video;
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

class CommContentController extends Controller
{
    use PHPRedisTrait;
    use BbsTrait;

    public function post(Request $request)
    {
        if (isset($request->params)) {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'content' => 'nullable',
                'thumbs' => 'nullable',
                'video' => 'nullable',
                'video_picture' => 'nullable',
                'category_id' => 'nullable',
                'location_name' => 'nullable',
            ])->validate();
            $content = $params['content'] ?? '';
            $videoPicture = $params['video_picture'] ?? '[]';
            $thumbs = $params['thumbs'] ?? '[]';
            $video = $params['video'] ?? '[]';
            $categoryId = $params['category_id'] ?? '';
            $locationName = $params['location_name'] ?? '';
            $insertData = [
                'thumbs' => $thumbs,
                'video' => $video,
                'video_picture' => $videoPicture,
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

    /**
     * 文章列表
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function lists(Request $request)
    {
        try {
            $params = ApiParamsTrait::parse($request->params);
            Validator::make($params, [
                'cid_1' => 'nullable',
                'cid_2' => 'nullable',
                'location_name' => 'nullable',
                'page' => 'nullable',
            ])->validate();
            // 一二级分类
            $cid1 = $params['cid_1'] ?? 0;
            $page = $params['page'] ?? 1;
            $locationName = $params['location_name'] ?? '';
            $cid2 = $params['cid_2'] ?? 0;
            // 得到一级分类help
            $help = $this->redis()->hGet('common_cate_help', "c_{$cid1}");
            $uid = $request->user()->id;
            if (in_array($help, ['focus'])) {
                $res = $this->$help($uid, $locationName, 6,$page);
            } else {
                $res = $this->other($request->user()->id, $locationName,$cid1, $cid2,6,$page);
            }
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);
        }
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
            $list = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
                ->where('community_bbs.id', $id)->orderBy('updated_at', 'desc')->get();
            $uid = $request->user()->id;
            $result = $this->proProcessData($uid, $list);
            return response()->json([
                'state' => 0,
                'data' => $result[0]??[]
            ]);
        }
        return [];
    }

    /**
     * 关注列表
     * @param $uid
     * @return Builder[]|Collection
     */
    private function focus($uid, $locationName = '',$perPage = 6, $page = 1)
    {
        $userList = CommFocus::where('user_id', $uid)->pluck('to_user_id');
        $paginator = CommBbs::query()
            ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
            ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel','video_picture')
            ->whereIn('author_id', $userList)->orderBy('updated_at', 'desc')
            ->simplePaginate($perPage, ['*'], '', $page);
        //加入视频列表
        $res['hasMorePages'] = $paginator->hasMorePages();
        $list = $paginator->items() ?? [];
        $result = $this->proProcessData($uid, $list);
        $res['bbs_list'] = $result;
        return $res;
    }

    /**
     * 其它类别
     * @param $uid
     * @param string $locationName
     * @param int $cid1
     * @param int $cid2
     * @param int $perPage
     * @param int $page
     * @return Collection|array
     */
    private function other($uid, $locationName = '',$cid1 = 0, $cid2 = 0, $perPage = 6, $page = 1): Collection|array
    {
        if ($cid2) {
            $model = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel','video_picture')
                ->where('category_id', $cid2)->orderBy('updated_at', 'desc');
            if ($locationName) {
                $model->where('users.location_name','like',"%{$locationName}%");
            }
            $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
            $data['hasMorePages'] = $paginator->hasMorePages();
            $list = $paginator->items();
            $result = $this->proProcessData($uid, $list);
            $data['bbs_list'] = $result;
            return $data;
        }
        if ($cid1) {
            $ids = $this->getChild($cid1, false);

            $model = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel','video_picture')
                ->whereIn('category_id', $ids)
                ->orderBy('updated_at', 'desc');
            if ($locationName) {
                $model->where('users.location_name','like',"%{$locationName}%");
            }
            $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
            $data['hasMorePages'] = $paginator->hasMorePages();
            $list = $paginator->items();

            $result = $this->proProcessData($uid, $list);
            $data['bbs_list'] = $result;

            return $data;
        }

        return [];
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
                $data = $item['childs'];
            }
        }
        if ($raw) {
            return $data;
        }
        return array_column($data, 'id');

    }
}