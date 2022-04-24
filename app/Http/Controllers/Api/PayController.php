<?php

namespace App\Http\Controllers\Api;

use App\TraitClass\ApiParamsTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PayController extends PayBaseController
{
    use ApiParamsTrait;
    public function entrance($channel): \Illuminate\Http\JsonResponse
    {
        $params = ApiParamsTrait::parse(request()->params ?? '');
        $validated = Validator::make($params, [
            'pay_id' => 'required|string',
            'type' => [
                'required',
                'string',
                Rule::in(['1', '2']),
            ],
        ])->validated();
//        dump($channel);
        (new $channel($validated))->pay();
        return response()->json($channel);
    }

    public function callback($channel)
    {

    }

}