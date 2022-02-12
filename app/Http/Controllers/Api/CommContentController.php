<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommBbs;
use App\Models\CommFocus;
use App\Models\LoginLog;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\BbsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\UserTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommContentController extends Controller
{
    use PHPRedisTrait;
    use BbsTrait;
    use UserTrait;

    /**
     * 文章发表
     * @param Request $request
     * @return array|JsonResponse
     */
    public function post(Request $request): JsonResponse|array
    {
        DB::beginTransaction();
        try {
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
            if ($thumbs) {
                $thumbsRaw = json_decode($thumbs,true);
                $thumbsData = [];
                foreach ($thumbsRaw as $item) {
                    $thumbsData[] = str_replace(env('RESOURCE_DOMAIN'),'',$item);
                }
                $thumbs = json_encode($thumbsData);
            }
            if ($video) {
                $videoRaw = json_decode($video,true);
                $videoData = [];
                foreach ($videoRaw as $itemVideo) {
                    $videoData[] = str_replace(env('RESOURCE_DOMAIN'),'',$itemVideo);
                }
                $video = json_encode($videoData);
            }
            if ($videoPicture) {
                $videoThumbsRaw = json_decode($videoPicture,true);
                $videoThumbsData = [];
                foreach ($videoThumbsRaw as $itemPic) {
                    $videoThumbsData[] = str_replace(env('RESOURCE_DOMAIN'),'',$itemPic);
                }
                $videoPicture = json_encode($videoThumbsData);
            }
            $insertData = [
                'thumbs' => $thumbs,
                'video' => $video,
                'video_picture' => $videoPicture,
                'category_id' => $categoryId,
                'author_id' => $request->user()->id,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            DB::table('community_bbs')->insertGetId($insertData);
            DB::commit();
            return response()->json([
                'state' => 0,
                'msg' => '发帖成功'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('bbsPost===' . $e->getMessage());
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);

        }
    }

    /**
     * 文章列表
     * @param Request $request
     * @return array|JsonResponse
     */
    public function lists(Request $request): JsonResponse|array
    {
        $params = ApiParamsTrait::parse($request->params);
        //Log::info('===COMMLIST===',[$params]);
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
        //Log::info('===COMMLIST-params==',[$params]);
        // 得到一级分类help
        $help = $this->redis()->hGet('common_cate_help', "c_{$cid1}");
        $uid = $request->user()->id;
        if (in_array($help, ['focus', 'hot'])) {
            $res = $this->$help($uid, $locationName, 6, $page);
        } else {
            $res = $this->other($request->user()->id, $locationName, $cid1, $cid2, 6, $page);
        }
        //Log::info('===CommContent===',[$res['bbs_list']]);
        $this->processArea($res['bbs_list']);
        return response()->json([
            'state' => 0,
            'data' => $res
        ]);
        /*try {

        } catch (Exception $e) {
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);
        }*/
    }

    /**
     * 处理地理数据
     * @param $data
     */
    private function processArea(&$data)
    {
        $ids = array_column($data, 'uid');
        $ids = array_filter($ids);
        $lastLogin = LoginLog::query()
            ->select('uid', DB::raw('max(id) as max_id'))->whereIn('uid', $ids)
            ->groupBy('uid')
            ->get()->toArray();
        if (!$lastLogin) {
            return;
        }
        $lastLoginIds = array_column($lastLogin, 'max_id');
        $areaInfo = LoginLog::query()->whereIn('id', $lastLoginIds)
            ->groupBy('uid')
            ->get()->toArray();
        if (!$areaInfo) {
            return;
        }
        $areaInfoMap = array_column($areaInfo, null, 'uid');
        foreach ($data as $k => $v) {
            $rawArea = $areaInfoMap[$v['uid']] ?? [];
            $data[$k]['location_name'] = '未知';
            if(!empty($rawArea)){
                $data[$k]['location_name'] = $this->getAreaNameFromUser($rawArea['area']);
            }
            /*$tmpArea = @json_decode($rawArea['area'] ?? '', true);
            $tmpArea = $tmpArea ?? [];
            $data[$k]['location_name'] = '未知';
            if(!empty($tmpArea)){
                $data[$k]['location_name'] = $tmpArea[2] ?: ($tmpArea[1] ?: ($tmpArea[0]));
            }*/
        }
    }

    /**
     * 详情
     * @param Request $request
     * @return array|JsonResponse
     */
    public function detail(Request $request): JsonResponse|array
    {
        $params = ApiParamsTrait::parse($request->params);
        Validator::make($params, [
            'id' => 'integer',
        ])->validate();
        $id = $params['id'] ?? 0;
        $list = CommBbs::query()
            ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
            ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
            ->where('community_bbs.id', $id)->orderBy('updated_at', 'desc')->get();
        $user = $request->user();
        $uid = $user->id;
        // 增加点击数
        CommBbs::query()->where('community_bbs.id', $id)->increment('views');
        //Log::info('==userLocationName1==',[$user]);
        $result = $this->proProcessData($uid, $list,$user);
        // 处理新文章通知
        $redis = $this->redis();
        $mask = $redis->get("c_{$list[0]['category_id']}");
        if ($mask == 'focus') {
            $keyMe = "status_me_focus_{$list[0]['user_id']}";
        } else {
            $keyMe = "status_me_{$mask}_$uid";
        }
        $redis->del($keyMe);
        return response()->json([
            'state' => 0,
            'data' => $result[0] ?? []
        ]);
        /*try {

        } catch (Exception $e) {
            return response()->json([
                'state' => -1,
                'msg' => $e->getMessage()
            ]);
        }*/
    }

    /**
     * 关注列表
     * @param $uid
     * @param string $locationName
     * @param int $perPage
     * @param int $page
     * @return Builder[]|Collection
     */
    private function focus($uid, $locationName = '', $perPage = 6, $page = 1)
    {
        $userList = CommFocus::where('user_id', $uid)->pluck('to_user_id');
        $model = CommBbs::query()
            ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
            ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel', 'video_picture')
            ->whereIn('author_id', $userList)->where('community_bbs.status',1)->orderBy('updated_at', 'desc');
        if ($locationName) {
            $locationName = mb_ereg_replace('市|自治区|县', '', $locationName);
            $model->where('users.location_name', 'like', "%{$locationName}%");
        }
        $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
        //加入视频列表
        $res['hasMorePages'] = $paginator->hasMorePages();
        $list = $paginator->items() ?? [];
        $result = $this->proProcessData($uid, $list);
        $res['bbs_list'] = $result;
        return $res;
    }

    /**
     * 最热
     * @param $uid
     * @param string $locationName
     * @param int $perPage
     * @param int $page
     * @return Builder[]|Collection
     */
    private function hot($uid, $locationName = '', $perPage = 6, $page = 1)
    {
        $model = CommBbs::query()
            ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
            ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel', 'video_picture')
            ->where('community_bbs.status',1)->orderBy('views', 'desc');
        if ($locationName) {
            $locationName = mb_ereg_replace('市|自治区|县', '', $locationName);
            $model->where('users.location_name', 'like', "%{$locationName}%");
        }
        $paginator = $model->simplePaginate($perPage, ['*'], '', $page);
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
    private function other($uid, $locationName = '', $cid1 = 0, $cid2 = 0, $perPage = 6, $page = 1): Collection|array
    {
        if ($cid2) {
            $model = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel', 'video_picture')
                ->where('category_id', $cid2)->where('community_bbs.status',1)->orderBy('updated_at', 'desc');
            if ($locationName) {
                $locationName = mb_ereg_replace('市|自治区|县', '', $locationName);
                $model->where('users.location_name', 'like', "%{$locationName}%");
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
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel', 'video_picture')
                ->whereIn('category_id', $ids)
                ->where('community_bbs.status',1)
                ->orderBy('updated_at', 'desc');
            if ($locationName) {
                $model->where('users.location_name', 'like', "%{$locationName}%");
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
                $data = $item['childs'] ?? [];
            }
        }
        if ($raw) {
            return $data;
        }
        return array_column($data, 'id');

    }
}