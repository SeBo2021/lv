<?php


namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Jobs\ProcessViewVideo;
use App\Models\Category;
use App\Models\Domain;
use App\Models\Video;
use App\Models\VideoShort;
use App\Models\ViewRecord;
use App\TraitClass\ApiParamsTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoShortController extends Controller
{
    use VideoTrait;
    use PHPRedisTrait;

    private array $mainCateAlias = [
        'short_hot',
        'limit_free',
        'short_rec'
    ];

    private array $cateMapAlias = [
        '-1' => 'sub_cat_1',
        '-2' => 'sub_cat_2',
        '-3' => 'sub_cat_3',
        '-4' => 'sub_cat_4',
        '-5' => 'sub_cat_5',
        '-6' => 'sub_cat_6',
        '-7' => 'sub_cat_7',
        '-8' => 'sub_cat_8',
    ];

    /**
     * 视频分类
     * @param Request $request
     * @return JsonResponse
     */
    public function cate(Request $request): JsonResponse
    {
        $raw = Category::whereIn('mask', $this->mainCateAlias)
            ->orderBy('sort', 'desc')
            ->select('id', 'name')
            ->get();
        $data = json_decode($raw, true);
        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }

    /**
     * 读取数据
     * @param $page
     * @param $uid
     * @param $startId
     * @param $cateId
     * @param $tagId
     * @return array
     */
    private function items($page, $uid, $startId,$cateId,$tagId)
    {
        $videoField = ['id', 'name', 'cid', 'cat','tag', 'restricted', 'sync', 'title', 'url', 'gold', 'duration', 'hls_url', 'dash_url', 'type', 'cover_img', 'views', 'likes', 'comments', 'updated_at'];
        $perPage = 8;
        $model = VideoShort::query();
        if ($cateId) {
            $cateWord = sprintf('"%s"',$cateId);
            $model->where('cat','like',$cateWord);
        }
        if ($tagId) {
            $tagWord = sprintf('"%s"',$tagId);
            $model->where('cat','like',$tagWord);
        }
        if ($startId) {
            $model->where('id','>',$startId);
        }

        $paginator = $model->simplePaginate($perPage, $videoField, 'shortLists', $page);
        $items = $paginator->items();
        $data = [];
        foreach ($items as $one) {
            $one = $this->handleShortVideoItems([$one], true)[0];
            $one['limit'] = 0;
            $viewRecord = $this->isShortLoveOrCollect($uid, $one['id']);
            $one['is_love'] = $viewRecord['is_love'] ?? 0;
            //是否收藏
            $one['is_collect'] = $viewRecord['is_collect'] ?? 0;
            $data[] = $one;
        }
        return [
            'list' => $data,
            'hasMorePages' => $paginator->hasMorePages()
        ];
    }

    //播放
    public function lists(Request $request)
    {
        // 业务逻辑
        try {
            $uid = $request->user()->id;
            $params = ApiParamsTrait::parse($request->params);
            $validated = Validator::make($params, [
                'start_id' => 'nullable',
                'keyword' => 'nullable',
                'cate_id' => 'nullable',
                'tag_id' => 'nullable',
                'sort' => 'nullable',
                'use_gold' => [
                    'nullable',
                    'string',
                    Rule::in(['1', '0']),
                ],
            ])->validated();
            $page = $params['page'] ?? 1;
            $cateId = $params['cate_id'] ?? "";
            $tagId = $params['tag_id'] ?? "";
            $starId = $validated['start_id'] ?? '0';
            $res = $this->items($page, $uid, $starId,$cateId,$tagId);
            return response()->json([
                'state' => 0,
                'data' => $res
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("shortLists", [$msg]);
            return response()->json([
                'state' => -1,
                'data' => $msg
            ]);
        }
    }

    //点赞
    public function like(Request $request)
    {
        try {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'like' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['like'];
            $redis = $this->redis();
            if ($is_love) {
                $one['is_love'] = $redis->set("short_is_love_{$user->id}_{$id}", 1);
                VideoShort::query()->where('id', $id)->increment('likes');
            } else {
                $one['is_love'] = $redis->del("short_is_love_{$user->id}_{$id}", 0);
                VideoShort::query()->where('id', $id)->decrement('likes');
            }
            $attributes = ['uid' => $user->id, 'vid' => $id];
            $values = ['is_love' => $is_love];
            ViewRecord::query()->updateOrInsert($attributes, $values);
            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionLike", [$msg]);
            return response()->json([
                'state' => -1,
                'msg' => '操作失败'
            ]);
        }
    }

    // 收藏
    public function collect(Request $request)
    {
        try {
            $user = $request->user();
            $params = ApiParamsTrait::parse($request->params);
            $rules = [
                'id' => 'required|integer',
                'collect' => 'required|integer',
            ];
            Validator::make($params, $rules)->validate();
            $id = $params['id'];
            $is_love = $params['collect'];
            $redis = $this->redis();


            if ($is_love) {
                $redis->set("short_is_collect_{$user->id}_{$id}", 1);
                Video::query()->where('id', $id)->increment('favors');
            } else {
                $redis->del("short_is_collect_{$user->id}_{$id}");
                Video::query()->where('id', $id)->decrement('favors');
            }
            $attributes = ['uid' => $user->id, 'vid' => $id];
            $values = ['is_love' => $is_love];
            ViewRecord::query()->updateOrInsert($attributes, $values);
            return response()->json([
                'state' => 0,
                'msg' => '操作成功'
            ]);
        } catch (Exception $exception) {
            $msg = $exception->getMessage();
            Log::error("actionLike", [$msg]);
        }
    }

    /**
     * 小视频处理
     * @param $lists
     * @param false $display_url
     * @param int $uid
     * @return mixed
     */
    public function handleShortVideoItems($lists, $display_url = false, $uid = 0): mixed
    {
        array_map(function ($item) use ($display_url,$uid){
            //$item = (array)$item;
            $domainSync = VideoTrait::getDomain($item['sync']);
            $item['cover_img'] = $domainSync . $item['cover_img'];
            $item['gold'] = $item['gold'] / $this->goldUnit;
            $item['views'] = $item['views'] > 0 ? $this->generateRandViews($item['views']) : $this->generateRandViews(rand(5, 9));
            $item['hls_url'] = $domainSync . $item['hls_url'];
            $item['preview_hls_url'] = $this->getPreviewPlayUrl($item['hls_url']);
            $item['dash_url'] = $domainSync . $item['dash_url'];
            $item['preview_dash_url'] = $this->getPreviewPlayUrl($item['dash_url'], 'dash');
            if (!$display_url) {
                unset($item['hls_url'], $item['dash_url']);
            }
            //是否点赞
            $viewRecord = $this->isShortLoveOrCollect($uid, $item['id']);
            $item['is_love'] = $viewRecord['is_love'] ?? 0;
            //是否收藏
            $item['is_collect'] = $viewRecord['is_collect'] ?? 0;
        },$lists);
        return $lists;
    }

    /**
     * 判断是否收藏或喜欢
     * @param int $uid
     * @param $vid
     * @return int[]
     */
    public function isShortLoveOrCollect($uid = 0, $vid = 0): array
    {
        $redis = $this->redis();
        $one['is_love'] = $redis->get("short_is_love_{$uid}_{$vid}") ?: 0;
        //是否收藏
        $one['is_collect'] = $redis->get("short_is_collect_{$uid}_{$vid}") ?: 0;
        return $one;
    }

}