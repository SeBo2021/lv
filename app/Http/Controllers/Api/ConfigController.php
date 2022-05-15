<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\TraitClass\AdTrait;
use App\TraitClass\PHPRedisTrait;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    use PHPRedisTrait,AdTrait;

    public function ack(): \Illuminate\Http\JsonResponse
    {
        $configKey = 'api_config';
        $configData = $this->redis()->get($configKey);
        $res = $configData ? json_decode($configData,true) : $this->getConfigDataFromDb();
        $this->frontFilterAd($res['open_screen_ads']);
        $this->frontFilterAd($res['activity_ads']);
        return response()->json([
            'state'=>0,
            'data'=>$res
        ]);

    }

}
