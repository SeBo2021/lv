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

    public function info(Request $request): JsonResponse
    {
        $raw = $this->redis()->get('common_cate');
        $data = json_decode($raw, true);
        foreach ($data as $k => $datum) {
            if ($datum['id'] >= 1) {
                $data[$k]['have_new'] = 1;
            } else {
                $data[$k]['have_new'] = 0;
            }
        }
        return response()->json([
            'state' => 0,
            'data' => $data
        ]);
    }
}
