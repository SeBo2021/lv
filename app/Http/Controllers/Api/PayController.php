<?php

namespace App\Http\Controllers\Api;

use App\TraitClass\ApiParamsTrait;

class PayController extends PayBaseController
{
    use ApiParamsTrait;
    public function entrance($channel): \Illuminate\Http\JsonResponse
    {
        //return $channel;
//        $return = $this->format(0, ['url' => $channel], '取出成功');
        return response()->json($channel);
    }

}