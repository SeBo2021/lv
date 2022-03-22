<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStatisticData extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair:statisticData {day?}';

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
        $paramDay = $this->argument('day');
        $data_at = $paramDay!==null ? date('Y-m-d',strtotime('-'.$paramDay.' day')) : date('Y-m-d');
        $date_time = strtotime($data_at);
        $redis = $this->redis();
        $Items = DB::table('login_log')->select('id',DB::raw('count(id) ids'),DB::raw('DATE_FORMAT(created_at,"%Y-%m-%d") date_at'),'channel_id','device_system')
            ->whereDate('created_at',$data_at)
            ->groupBy(['channel_id','device_system','date_at'])
            ->orderByDesc('created_at')
            ->get();
        //dump($rechargeItems);
        $idsArr = [];
        foreach ($Items as $item)
        {
            //$channel_day_statistics_key = 'channel_day_statistics:'.$item->channel_id.':'.$item->date_at;
            $statistic_day_key = 'statistic_day:'.$item->channel_id.':'.$item->device_system.':'.$date_time;
            //$share_ratio = (int)$redis->hGet(str_replace('laravel_database_','',$statistic_day_key),'share_ratio');
            /* $hashKeys = [
                'date_at' => $item->date_at,
                'channel_id' => $item->channel_id,
                'channel_pid' => $item->channel_pid,
                'total_amount' => $item->sum_amount,
                'total_orders' => $item->ids,
                'order_index' => $item->ids,
                'last_order_id' => $item->id,
                'usage_index' => $item->id,
                //share_ratio' => $item->id,
                'share_amount' => round(($item->sum_amount * $share_ratio)/100),
                'total_recharge_amount' => $item->sum_amount,
                'orders' => $item->ids,
            ]; */
            $hashKeys=[
                'active_users' => $item->ids,
                'channel_id' => $item->channel_id,
                'device_system' => $item->device_system,
                'at_time' => $date_time,
            ];
            $idsArr[] = $item->ids;
            $this->info($item->channel_id.':'.$item->device_system.':'.$item->ids.'######执行成功######');
            $redis->hMSet($statistic_day_key,$hashKeys);
        }

        $this->info('######执行成功######'.array_sum($idsArr));
        return 0;
    }
}
