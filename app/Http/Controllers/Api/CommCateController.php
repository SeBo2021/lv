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
        $data = $this->redis()->get('common_cate');
        return response()->json([
            'state' => 0,
            'data' => json_decode($data, true)
        ]);
    }
}
