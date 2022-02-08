<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncInstallDataFromUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:installUserFromUsers {day?}';

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
        $usersByYesterday = DB::table('users')->select('channel_id',DB::raw('count(id) as users,date_format(created_at, "%Y-%m-%d") as date_at'))->whereDate('created_at', $data_at)->groupBy(['channel_id','date_at'])
            ->get();
        foreach ($usersByYesterday as $item){
            DB::table('channel_day_statistics')
                ->where('channel_id',$item->channel_id)
                ->where('date_at',$item->date_at)
                ->update(['install_real'=>$item->users]);
        }
        if($paramDay){
            $this->info('######同步前第'.$paramDay.'天用户安装量数据执行成功######');
        }else{
            $this->info('######同步当天用户安装量数据执行成功######');
        }
        return 0;
    }
}
