<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEncryptVideo;
use App\TraitClass\VideoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class encryptVideoHlsFile extends Command
{
    use VideoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:videoHlsFile';

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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $table = 'video';
        $items = DB::table($table)
            ->whereIn('id',['3'])
            ->get(['id','hls_url','url']);
        //$domain =str_replace('https','http',env('RESOURCE_DOMAIN'));
        
        foreach ($items as $item)
        {
            ProcessEncryptVideo::dispatch($item)->delay(now()->addMinutes(1));
            $this->info('######视频ID：'.$item->id.'执行成功######');
        }

        $this->info('######执行成功######');
        return 0;
    }

}
