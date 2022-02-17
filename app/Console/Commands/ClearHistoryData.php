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
        $paramDay = $this->argument('day') ?? 1;
        $time_at = strtotime('-'.$paramDay.' day');
        $data_at = $paramDay!==null ? date('Y-m-d',$time_at) : date('Y-m-d');
        //下载数据
        DB::table('app_download')->whereDate('created_at', $data_at)->delete();
        //观看数据 (7天)
        DB::table('view_history')->where('time_at', '<',strtotime('-7 day'))->delete();
        $this->info('######清除前第'.$paramDay.'天数据执行成功######');
        return 0;
    }
}
