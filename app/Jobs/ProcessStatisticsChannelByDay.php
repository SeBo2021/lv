<?php

namespace App\Jobs;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessStatisticsChannelByDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PHPRedisTrait;

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
        $statisticTable = 'channel_day_statistics';
        $redis = $this->redis();

        if($channelInfo){
            $date_at = date('Y-m-d');
            $channel_day_statistics_key = 'channel_day_statistics:'.$channel_id.':'.$date_at;
            $has = DB::table($statisticTable)
                ->where('channel_id',$channelInfo->id)
                ->whereDate('date_at',$date_at)
                ->first();
            $level_one = explode(',', $channelInfo->level_one);

            if(!$has){//是否统计过
                $isUsage = !in_array(1,$level_one);
                $insertData = [
                    'channel_name' => $channelInfo->name,
                    'channel_promotion_code' => $channelInfo->promotion_code,
                    'channel_id' => $channel_id,
                    'channel_pid' => $channelInfo->pid,
                    'channel_type' => $channelInfo->type,
                    'channel_code' => $channelInfo->number,
                    'share_ratio' => $channelInfo->share_ratio,
                    'share_amount' => $isUsage ? round(($this->orderInfo->amount * $channelInfo->share_ratio)/100,2) : 0,
                    'total_recharge_amount' => $isUsage ? $this->orderInfo->amount : 0,
                    'total_amount' => $this->orderInfo->amount,
                    'total_orders' =>  1,
                    'orders' =>  $isUsage ? 1 : 0,
                    'date_at' => $date_at,
                    'last_order_id' => $this->orderInfo->id,
                    'order_index' => 1,
                    'usage_index' => $isUsage ? 1 : 0,
                ];
                DB::table($statisticTable)->insert($insertData);
                $redis->hMSet($channel_day_statistics_key,$insertData);
            }else{ //累计
                $order_index = $has->order_index + 1;
                //是否有纳入统计条目
                $usage_index = 0;
                $level_one_limits = 31;

                if($has->orders < $level_one_limits){
                    if(!in_array($order_index,$level_one)){
                        $usage_index = $order_index;
                    }
                }else{
                    if($order_index == $level_one_limits){
                        $usage_index = $level_one_limits;
                    }else{
                        if($has->usage_index >= $level_one_limits){
                            $second_index = $has->usage_index + $channelInfo->level_two+1;
                            if($second_index === $order_index){
                                $usage_index = $second_index;
                            }
                        }
                    }
                }
                $updateData = [
                    'total_amount' => $has->total_amount + $this->orderInfo->amount,
                    'total_orders' => $has->total_orders + 1,
                    'order_index' => $order_index,
                    'last_order_id' => $this->orderInfo->id,
                ];
                if($usage_index>0){
                    $updateData['usage_index'] = $usage_index;
                    $updateData['share_ratio'] = $channelInfo->share_ratio;
                    $updateData['share_amount'] = round(($this->orderInfo->amount * $channelInfo->share_ratio)/100 + $has->share_amount,2);
                    $updateData['total_recharge_amount'] = $has->total_recharge_amount + $this->orderInfo->amount;
                    $updateData['orders'] = $has->orders + 1;
                }
                DB::table($statisticTable)
                    ->where('channel_promotion_code',$channelInfo->promotion_code)
                    ->whereDate('date_at',$date_at)
                    ->update($updateData);
                $redis->hMSet($channel_day_statistics_key,$updateData);
            }
        }

    }
}
