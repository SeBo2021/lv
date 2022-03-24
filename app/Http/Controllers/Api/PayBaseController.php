<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gold;
use App\Models\MemberCard;
use App\TraitClass\PHPRedisTrait;

/**
 * 支付基础类
 * Class CJController
 * @package App\Http\Controllers\Api
 */
class PayBaseController extends Controller
{
    use PHPRedisTrait;
    /**
     * 返回通道代码
     * @param $type
     * @param $goodsId
     * @param $channel
     * @return mixed
     */
    protected function getOwnMethod($type, $goodsId, $channel): mixed
    {
        if ($type == 1) {
            $memberCardData = $this->getMemberCardData();
            if ($channel == 1) {
                return $memberCardData[$goodsId]['zfb_channel'];
            }
            return $memberCardData[$goodsId]['wx_channel'];
        } elseif ($type == 2) {
            $goldCardData = $this->getGoldData();
            if ($channel == 1) {
                return $goldCardData[$goodsId]['zfb_channel'];
            }
            return $goldCardData[$goodsId]['wx_channel'];
        }
    }

    /**
     * 返回支付渠道
     * @param $type
     * @param $goodsId
     * @param $channel
     * @return mixed
     */
    protected function getOwnCode($type, $goodsId, $channel): mixed
    {
        if ($type == 1) {
            $memberCardData = $this->getMemberCardData();
            // var_dump($memberCardData[$goodsId]);
            if ($channel == 1) {
                return $memberCardData[$goodsId]['zfb_action_id'];
            }
            return $memberCardData[$goodsId]['wx_action_id'];
        } elseif ($type == 2) {
            $goldCardData = $this->getGoldData();
            if ($channel == 1) {
                return $goldCardData[$goodsId]['zfb_action_id'];
            }
            return $goldCardData[$goodsId]['wx_action_id'];
        }
    }

    private function getMemberCardData() {
        $memberCardApiKey = "api_recharge_member_card";
        $cacheData = $this->redis()->get($memberCardApiKey);
        if ($cacheData) {
            $memberCardData = json_decode($cacheData, true);
        } else {
            $memberCardInfo = MemberCard::query()
                // ->where('status', 1)
                ->select(['zfb_channel', 'wx_channel','zfb_action_id','wx_action_id', 'id'])
                ->get()
                ->toArray();
            $memberCardData = array_column($memberCardInfo, null, 'id');
            $this->redis()->set($memberCardApiKey, json_encode($memberCardData, JSON_UNESCAPED_UNICODE));
        }
        return $memberCardData;
    }

    private function getGoldData() {
        $goldCardApiKey = "api_recharge_member_gold";
        $cacheData = $this->redis()->get($goldCardApiKey);
        if ($cacheData) {
            $goldCardData = json_decode($cacheData, true);
        } else {
            $goldCardInfo = Gold::query()
                // ->where('status', 1)
                ->select(['zfb_channel', 'wx_channel','zfb_action_id','wx_action_id', 'id'])
                ->get()
                ->toArray();
            $goldCardData = array_column($goldCardInfo, null, 'id');
            $this->redis()->set($goldCardApiKey, json_encode($goldCardData, JSON_UNESCAPED_UNICODE));
        }
        return $goldCardData;
    }
}