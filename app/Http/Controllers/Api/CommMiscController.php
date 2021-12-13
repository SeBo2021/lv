<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\TraitClass\CityTrait;
use App\TraitClass\UploadTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 社区其它接口
 * Class CommMiscController
 * @package App\Http\Controllers\Api
 */
class CommMiscController extends Controller
{
    use UploadTrait, CityTrait;

    /**
     * 城市信息
     * @return JsonResponse
     */
    public function city(): JsonResponse
    {
        return response()->json([
            'state' => 0,
            'data' => json_decode($this->cityInfo(), true)
        ]);
    }

    /**
     * 上传资源
     * @param Request $request
     * @return JsonResponse
     */
    public function res(Request $request): JsonResponse
    {
        try {
            $r = $this->upFile($request);
            return response()->json([
                'state' => 0,
                'data' => [
                    'path' => env('RESOURCE_DOMAIN') . $r['path']
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'state' => -1,
                'data' => $e->getMessage(),
            ]);
        }

    }
}
