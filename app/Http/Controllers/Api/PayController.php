<?php

namespace App\Http\Controllers\Api;

class PayController extends PayBaseController
{
    public function entrance($channel)
    {
        //return $channel;
        return response($channel);
    }

}