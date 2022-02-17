<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAppDownloadData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:appDownloadData {day?}';

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
        $data_at = $paramDay!==null ? date('Y-m-d',strtotime('-'.$paramDay.' day')) : date('Y-m-d');
        DB::table('app_download')->whereDate('created_at', $data_at)->delete();
        $this->info('######清除前第'.$paramDay.'天下载数据执行成功######');
        return 0;
    }
}
