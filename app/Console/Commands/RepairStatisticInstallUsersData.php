<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStatisticInstallUsersData extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair:statisticInstallUsersData {day?}';

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
        $at_time = strtotime($data_at);
        $usersByYesterday = DB::table('users')->select('channel_id','created_at','device_system',DB::raw('count(id) as users'))->whereBetween('created_at', [$data_at.' 00:00:00',$data_at.' 23:59:59'])->groupBy(['channel_id','device_system'])
            ->get();
        foreach ($usersByYesterday as $item){
            DB::table('statistic_day')
                ->where('channel_id',$item->channel_id)
                ->where('device_system',$item->device_system)
                ->where('at_time',$at_time)
                ->update(['install'=>$item->users]);
        }

        /* $channelIds = DB::table('channels')->pluck('id');
        DB::table('statistic_day')->whereNotIn('channel_id',$channelIds)->delete(); */
        $items = DB::table('channel_day_statistics')->where('channel_id',0)->get(['channel_id','device_system','access','hits','install','active_users','active_view_users','login_number','date_at']);
        foreach($items as $item){
            $insertData = [
                'channel_id'=>$item->channel_id,
                'device_system'=>$item->device_system,
                'access'=>$item->device_system,
                'hits'=>$item->hits,
                'install'=>$item->install,
                'active_users'=>$item->install,
                'active_view_users'=>$item->install,
                'login_number'=>$item->install,
                'at_time'=>strtotime($item->date_at),
            ];
            DB::table('statistic_day')->insert($insertData);
        }


        if($paramDay){
            $this->info('######同步前第'.$paramDay.'天用户安装量数据执行成功######');
        }else{
            $this->info('######同步当天用户安装量数据执行成功######');
        }
        return 0;
    }
}
