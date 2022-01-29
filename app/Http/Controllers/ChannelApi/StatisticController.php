<?php

namespace App\Http\Controllers\ChannelApi;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatisticController extends \App\Http\Controllers\Controller
{

    public function index(Request $request)
    {
        $json = [];
        return response()->json($json);
    }
}