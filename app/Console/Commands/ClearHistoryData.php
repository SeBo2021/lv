<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearHistoryData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:historyData {day?}';

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
        $paramDay = $this->argument('day') ?? 3;
        //登录日志
        $delLoginLogTime = strtotime('-30 day');
        DB::table('login_log')->whereDate('created_at', '<',date('Y-m-d H:i:s',$delLoginLogTime))->delete();
        $this->info('######清除登录日志30天前数据执行成功######');
        //观看数据 (7天)
        DB::table('view_history')->where('time_at', '<',strtotime('-'.$paramDay.' day'))->delete();
        $this->info('######清除观看历史前'.$paramDay.'天数据执行成功######');
        return 0;
    }
}
