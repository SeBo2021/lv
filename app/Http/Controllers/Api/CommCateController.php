<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommCateController extends Controller
{
    use PHPRedisTrait, GoldTrait, VideoTrait;

    /**
     * 板块数据
     * @param Request $request
     * @return JsonResponse
     */
    public function info(Request $request): JsonResponse
    {
        $raw = $this->redis()->get('common_cate');
        $data = json_decode($raw, true);
        foreach ($data as $k => $datum) {
            $data[$k]['have_new'] = $this->checkHaveNew($request->user()->id,$datum['mark']);
        }
        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }

    /**
     * 检查是否有最新文章
     * @param $uid
     * @param $tag
     * @return int
     */
    private function checkHaveNew($uid,$tag): int
    {
        $keyMe = "status_me_{$tag}_$uid";
        $keyCate = "status_cate_{$tag}";
        $meTime = $this->redis()->get($keyMe)?:-1;
        $cateTime = $this->redis()->get($keyCate)?:-2;
        if ($meTime < $cateTime) {
            return 1;
        }
        return 0;
    }
}
