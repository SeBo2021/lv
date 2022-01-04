<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStatisticsChannelCps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderInfo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($orderInfo)
    {
        $this->orderInfo = $orderInfo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //$amount = $this->orderInfo->amount;
        $channel_id = $this->orderInfo->channel_id ?? 0;
        $channelInfo = DB::table('channels')->where('id',$channel_id)->first();

        if(($channelInfo->status == 1) && ($channelInfo->type==2)){ //正常的CPS渠道
            $date_at = date('Y-m-d');
            $has = DB::connection('channel_mysql')->table('channel_cps')
                ->where('promotion_code',$channelInfo->promotion_code)
                ->whereDate('date_at',$date_at)
                ->first();
            $level_one = explode(',', $channelInfo->level_one);

            if(!$has){//是否统计过
                $isUsage = !in_array(1,$level_one);
                DB::connection('channel_mysql')->table('channel_cps')->insert([
                    'name' => $channelInfo->name,
                    'promotion_code' => $channelInfo->promotion_code,
                    'channel_id' => $channel_id,
                    'channel_code' => $channelInfo->number,
                    'share_ratio' => $channelInfo->share_ratio,
                    'share_amount' => $isUsage ? round(($this->orderInfo->amount * $channelInfo->share_ratio)/100,2) : 0,
                    'total_recharge_amount' => $isUsage ? $this->orderInfo->amount : 0,
                    'orders' =>  $isUsage ? 1 : 0,
                    'date_at' => $date_at,
                    'last_order_id' => $this->orderInfo->id,
                    'order_index' => 1,
                    'usage_index' => $isUsage ? 1 : 0,
                ]);
            }else{ //累计
                $order_index = $has->order_index + 1;
                //是否有纳入统计条目
                if($has->orders < 11){
                    if(!in_array($order_index,$level_one)){
                        $usage_index = $order_index;
                    }
                }else{
                    if($has->orders == 11){
                        $usage_index = 11;
                    }

                    if($has->usage_index >= 11){
                        $usage_index = $has->usage_index + $channelInfo->level_two;
                    }
                }
                $updateData = [
                    'order_index' => $order_index,
                    'last_order_id' => $this->orderInfo->id,
                ];
                if(isset($usage_index)){
                    $updateData['usage_index'] = $usage_index;
                    $updateData['share_ratio'] = $channelInfo->share_ratio;
                    $updateData['share_amount'] = round(($this->orderInfo->amount * $channelInfo->share_ratio)/100 + $has->share_amount,2);
                    $updateData['total_recharge_amount'] = $has->total_recharge_amount + $this->orderInfo->amount;
                    $updateData['orders'] = $has->orders + 1;
                }
                DB::connection('channel_mysql')->table('channel_cps')
                    ->where('promotion_code',$channelInfo->promotion_code)
                    ->whereDate('date_at',$date_at)
                    ->update($updateData);
            }
        }

    }
}
