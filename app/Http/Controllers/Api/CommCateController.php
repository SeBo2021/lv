<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\TraitClass\CommTrait;
use App\TraitClass\GoldTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommCateController extends Controller
{
    use PHPRedisTrait, GoldTrait, VideoTrait, CommTrait;

    /**
     * 板块数据
     * @param Request $request
     * @return JsonResponse
     */
    public function info(Request $request): JsonResponse
    {
        $user = $request->user();
        /*$raw = $this->redis()->get('common_cate');
        $data = @json_decode($raw, true);
        $data = $data ?? [];*/
        $data = $this->getCommCate();
        foreach ($data as $k => $datum) {
            if ($datum['mark'] == 'focus') {
                $data[$k]['have_new'] = $this->checkFocusNew($user->id);
            } else {
                $data[$k]['have_new'] = $this->checkHaveNew($user->id, $datum['mark']);
            }
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
        $keyMe = "status_me_".$tag."_".$uid;
        $exist = $this->redis()->get($keyMe);
        if ($exist) {
            return 1;
        }
        return 0;
        /*if($tag == 'message'){
            return $exist ? 0 : 1;
        }else{
            if ($exist) {
                return 1;
            }
            return 0;
        }*/
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
