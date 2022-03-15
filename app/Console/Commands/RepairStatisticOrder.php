<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStatisticOrder extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair:statisticOrder';

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
        $rechargeItems = DB::table('recharge')->select('id',DB::raw('sum(amount) sum_amount'),DB::raw('count(id) ids'),DB::raw('DATE_FORMAT(created_at,"%Y-%m-%d") date_at'),'channel_id','channel_pid')
            ->whereDate('created_at',date('Y-m-d'))
            ->groupBy(['channel_id','date_at'])
            ->orderByDesc('created_at')
            ->get();
        //dump($rechargeItems);
        foreach ($rechargeItems as $item)
        {
            $channel_day_statistics_key = 'channel_day_statistics:'.$item->channel_id.':'.$item->date_at;
            $share_ratio = (int)$redis->hGet(str_replace('laravel_database_','',$channel_day_statistics_key),'share_ratio');
            $hashKeys = [
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
            ];
            $redis->hMSet($channel_day_statistics_key,$hashKeys);
        }

        $this->info('######执行成功######');
        return 0;
    }
}
