<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairVideo extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair:video';

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
        $Items = DB::table('video')->get(['id','hls_url']);
        $bar = $this->output->createProgressBar(count($Items));

        $bar->start();
        foreach ($Items as $item)
        {
            $hls = str_replace('_0_1000','',$item->hls_url);
            DB::table('video')->where('id',$item->id)->update(['hls_url'=>$hls]);
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
