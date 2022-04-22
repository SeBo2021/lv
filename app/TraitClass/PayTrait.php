<?php

namespace App\TraitClass;


use App\Jobs\ProcessMemberCard;
use App\Jobs\ProcessStatisticsChannelByDay;
use App\Models\Gold;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\PayLog;
use App\Models\Recharge;
use App\Models\RechargeChannel;
use App\Models\User;
use App\Models\Video;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;

trait PayTrait
{
    use ChannelTrait;

    public static function getPayTypeCode()
    {
        $payChannel = env('PAY_CHANNEL','');
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public static function getPayMethod()
    {
        $payChannel = env('PAY_CHANNEL','');
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public static function getAtionPayCode()
    {
        $payChannel = env('PAY_CHANNEL','');
        foreach (explode(',',$payChannel) as $v) {
            $data[$v] = [
                'id' => $v,
                'name' => $v,
            ];
        }
        return $data;
    }

    public function getAllPayChannel(){
        $data[] = ['id'=>'0','name'=>'全部'];
        $raw = RechargeChannel::where('status',1)->get();
        foreach ($raw as $v) {
            $data[] = ['id'=>$v->id,'name'=>$v->remark];
        }
        return $data;
    }
    /**
     * 返回支付类型标识
     * @param string $flag
     * @return string
     */
    public static function getPayType($flag=''): string
    {
        $payTypes = [
            'DBS' => '1',
        ];
        return $payTypes[$flag]??'0';
    }

    /**
     * 生成订单号
     * @return string
     */
    public static function getPayNumber(): string
    {
        return 'JB'.time().rand(10000,99999);
    }

    /**
     * vip信息表
     * @param $cardId
     * @return Builder|Builder[]|Collection|Model|null
     */
    private function getVipInfo($cardId): Model|Collection|Builder|array|null
    {
        return MemberCard::query()->find($cardId)?->toArray();
    }

    /**
     * gold信息表
     * @param $Id
     * @return Builder|Builder[]|Collection|Model|null
     */
    private function getGoldInfo($Id): Model|Collection|Builder|array|null
    {
        return Gold::query()->find($Id)?->toArray();
    }

    /**
     * 视频信息
     * @param $goodsId
     * @return Model|Collection|Builder|array|null
     */
    private function getGoodsInfo($goodsId): Model|Collection|Builder|array|null
    {
        return Video::query()->find($goodsId)?->toArray();
    }

    /**
     * 处理视频购买
     * @param $goodsId
     * @return Model|Collection|Builder|array|null
     */
    private function buyVideo($goodsId): Model|Collection|Builder|array|null
    {
        // return Video::query()->find($goodsId)?->toArray();
        return [];
    }

    /**
     * 处理骚豆购买
     * @param $id
     * @param $uid
     * @return Model|Collection|Builder|array|null
     */
    private function buyGold($id,$uid): Model|Collection|Builder|array|null
    {
        $info = Gold::query()->find($id)?->toArray();
        User::query()->find($uid)->update(
            ['gold' =>DB::raw("gold + {$info['money']}") ]
        );
        Cache::forget("cachedUser.{$uid}");
        return [];
    }

    /**
     * 处理vip购买
     * @param $goodsId
     * @param $uid
     * @return Model|Collection|Builder|array|null
     */
    private function buyVip($goodsId,$uid): Model|Collection|Builder|array|null
    {
        $cardInfo = MemberCard::query()
            ->find($goodsId,['id','value','real_value','expired_hours']);
        if($cardInfo->expired_hours > 0) {
            $expiredTime = $cardInfo->expired_hours * 3600 + time();
            $expiredAt = date('Y-m-d H:i:s',$expiredTime);
        }
        $user = User::query()->findOrFail($uid);
        $member_card_type = !empty($user->member_card_type) ? (array)$user->member_card_type : [];
        $member_card_type[] = $cardInfo->id;
        $vip = max($member_card_type);
        $updateMember = implode(',',$member_card_type);

        $vipExpired = MemberCard::query()->select(DB::raw('SUM(IF(expired_hours>0,expired_hours,10*365*24)) as expired_hours'))->whereIn('id',$member_card_type)->value('expired_hours') *3600;
        $r = User::query()->where('id',$uid)->update([
            'member_card_type' => $updateMember,
            'vip'=>$vip,
            'vip_start_last' => time(), // 最后vip开通时间
            'vip_expired' => $vipExpired
        ]);

        Log::info('pay_vip_update===', [[$user->id,$user->member_card_type],[
            'member_card_type' => $updateMember,
            'vip'=>$vip,
            'vip_start_last' => time(), // 最后vip开通时间
            'vip_expired' => $vipExpired
        ],$r]);//vip更新日志
        //队列执行
        /*if($cardInfo->expired_hours >= 0) {
            $job = new ProcessMemberCard($user->id,$cardInfo->id,($cardInfo->expired_hours?:10*365*34)*60*60);
            app(Dispatcher::class)->dispatchNow($job);
        }*/
        Cache::forget("cachedUser.{$user->id}");
        return [
            'expired_at' => $expiredAt??false
        ];
    }

    /**
     * 订单更新
     * @param $tradeNo
     * @param array $jsonResp
     * @param $userInfo
     * @throws Exception
     */
    private function orderUpdate($tradeNo,$jsonResp = []): void
    {
        $nowData = date('Y-m-d H:i:s');
        $payModel = PayLog::query()->where('number',$tradeNo);
        $payInfo = $payModel->first();
        $status = $payInfo->status ?? 0;
        if ($status == 1){
            return;
        }
        $payModel->update([
            'response_info' => json_encode($jsonResp),
            'status' => 1,
            'updated_at' => $nowData,
        ]);
        $orderId = $payInfo->order_id??0;
        $orderModel = Order::query()->where('id',$orderId);
        $orderModel->update([
            'status' => 1,
            'updated_at' => $nowData,
        ]);


        $orderInfo = $orderModel->first();
        //########渠道CPS日统计########
        ProcessStatisticsChannelByDay::dispatchAfterResponse($orderInfo);
        //#############################

        $method = match ($orderInfo->type) {
            1 => 'buyVip',
            2 => 'buyGold',
            3 => 'buyVideo',
        };
        $biz = $this->$method($orderInfo->type_id??0,$payInfo->uid);
        $channelInfo = $this->getChannelInfoById($orderInfo->channel_id);
        $chargeData = [
            'type' => $orderInfo->type??1,
            'uid' => $orderInfo->uid,
            'status' => 1,
            'amount' => $orderInfo->amount,
            'device_system' => $payInfo->device_system,
            'channel_id' => $orderInfo->channel_id,
            'channel_pid' => $orderInfo->channel_pid ?? 0,
            'order_id' => $orderInfo->id,
            'pay_method' => $payInfo->pay_method??1,
            'channel_code' => $payInfo->channel_code??'',
            'channel_principal' => $channelInfo->principal??'',
            'created_at' => $nowData,
            'updated_at' => $nowData,
        ];
        if ($expiredAt = $biz['expired_at']??false) {
            $chargeData['expired_at'] = $expiredAt;
        }
        Recharge::query()->create($chargeData);
    }

    /**
     * 得到支付信息
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function getPayEnv(): mixed
    {
        $payEnv = cache()->get('payEnv');
        if (!$payEnv) {
            $payEnv = RechargeChannel::query()
                ->where('status',1)
                ->get()?->toArray();
            $payEnv = array_column($payEnv,null,'name');
            cache()->set('payEnv',$payEnv);
        }
        return $payEnv;

    }

    public function getPayChannels(): array
    {
        return RechargeChannel::query()->pluck('remark','id')->all();
    }
}