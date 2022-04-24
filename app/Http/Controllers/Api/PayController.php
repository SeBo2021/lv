<?php

namespace App\Http\Controllers\Api;

use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\Request;

class PayController extends PayBaseController
{
    use ApiParamsTrait;
    public function entrance(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($request->all());
    }

}