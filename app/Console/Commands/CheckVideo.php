<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckVideo extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:video {tableName?}';

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
        $paramTableName = $this->argument('tableName')??'video';
        $Items = DB::table($paramTableName)->get(['id','url','hls_url']);
        $bar = $this->output->createProgressBar(count($Items));

        $bar->start();
        $num = 1;
        foreach ($Items as $item)
        {
            $urlName = pathinfo($item->url);
            $hlsUrlName = pathinfo($item->hls_url);
            if($urlName['filename']!=$hlsUrlName['filename']){
                $this->info($item->id.'-'.$item->url.'-'.$item->hls_url);
                $this->info($num);
                ++$num;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行完成######');
        return 0;
    }
}
