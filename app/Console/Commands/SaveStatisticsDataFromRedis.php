<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SaveStatisticsDataFromRedis extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save:statisticData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $redis = $this->redis();
        $statistic_day_keys = $redis->keys('*statistic_day:*');
        $yesterdayTime = strtotime(date('Y-m-d',strtotime('-1 day')));
        //$yesterdayDate = date('Y-m-d',$yesterdayTime);
        foreach ($statistic_day_keys as $statistic_day_key){
            $realKey = str_replace('laravel_database_','',$statistic_day_key);
            $channelStatisticItem = $redis->hGetAll($realKey);
            $channel_id = $channelStatisticItem['channel_id'] ?? 0;
            $device_system = $channelStatisticItem['device_system'] ?? 0;
            $at_time = $channelStatisticItem['at_time'] ?? 0;
            if($at_time>0 && $device_system>0){
                DB::table('statistic_day')
                    ->where('channel_id',$channel_id)
                    ->where('device_system',$device_system)
                    ->where('at_time',$at_time)
                    ->updateOrInsert(['channel_id'=>$channel_id,'device_system'=>$device_system,'at_time'=>$at_time],$channelStatisticItem);   
            }
            if($at_time<=$yesterdayTime){
                $redis->del($realKey);
            }    
        }

        $channel_day_statistics_keys = $redis->keys('*channel_day_statistics:*');
        foreach ($channel_day_statistics_keys as $channel_day_statistics_key){
            $noPrefixKey = str_replace('laravel_database_','',$channel_day_statistics_key);
            $item = $redis->hGetAll($noPrefixKey);
            $channel_id = $item['channel_id'] ?? 0;
            $date_at = $item['date_at'] ?? 0;
            if($channel_id>0){
                DB::table('channel_day_statistics')
                    ->where('channel_id',$channel_id)
                    ->where('date_at',$date_at)
                    ->updateOrInsert(['channel_id'=>$channel_id,'date_at'=>$date_at],$item);
                if(strtotime($date_at) <= $yesterdayTime){
                    $redis->del($noPrefixKey);
                }
            }
        }

        $this->info('######执行成功######');
        return 0;
    }
}
