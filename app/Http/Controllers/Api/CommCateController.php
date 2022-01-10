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
            if ($datum['mark'] == 'focus') {
                $data[$k]['have_new'] = $this->checkFocusNew($request->user()->id);
            } else {
                $data[$k]['have_new'] = $this->checkHaveNew($request->user()->id, $datum['mark']);
            }
            //is_allow_post 关闭app发贴功能

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
    private function checkHaveNew($uid, $tag): int
    {
        $keyMe = "status_me_{$tag}_$uid";
        $exist = $this->redis()->get($keyMe);
        if ($exist) {
            return 1;
        }
        return 0;
    }

    /**
     * 检查是否有最新文章
     * @param $uid
     * @return int
     */
    private function checkFocusNew($uid): int
    {
        $keyMe = "status_me_focus_{$uid}";
        $exist = $this->redis()->get($keyMe);
        if ($exist) {
            return 1;
        }
        return 0;
    }
}
