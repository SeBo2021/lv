<?php

namespace App\Console\Commands;

use App\TraitClass\ChannelTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChannelStatisticByDay extends Command
{
    use ChannelTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'channel:day';

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
        $this->initStatisticsByDay();
        $this->info('######渠道日统计初始化数据执行成功######');
        return 0;
    }
}
