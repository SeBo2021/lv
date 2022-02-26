<?php

namespace App\Console\Commands;

use App\TraitClass\VideoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class encryptVideoHlsFileByLocal extends Command
{
    use VideoTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:videoHlsFileByLocal';

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
            //->whereIn('id',['61'])
            ->whereIn('id',['61'])
            ->get(['id','hls_url','sync']);
        //$domain =str_replace('https','http',env('RESOURCE_DOMAIN'));
        
        foreach ($items as $item)
        {
            $pathInfo = pathinfo($item->hls_url);
            $fileDirname = str_replace('/storage','/public',$pathInfo['dirname']);
            //$previewFile = $fileDirname.'/preview.m3u8';
            /*if(Storage::disk('sftp')->exists($previewFile)){
                Storage::disk('sftp')->delete($previewFile);
            }*/
            $allFiles = Storage::disk('local')->files($fileDirname);
            /*foreach ($allFiles as $hlsFile){
                $content = Storage::disk('sftp')->get($hlsFile);
                Storage::disk('local')->put($hlsFile,$content);
                //$this->info($hlsFile);
            }*/
            //加密
            $format = new \FFMpeg\Format\Video\X264();
            $format->setAdditionalParameters([
                '-hls_list_size',0, //设置播放列表保存的最多条目，设置为0会保存有所片信息，默认值为5
                '-vcodec', 'copy','-acodec', 'copy', //跳过编码
            ]);
            $encryptKey = HLSExporter::generateEncryptionKey();
            Storage::disk('local')->put($fileDirname.'/secret.key',$encryptKey);
            $initHlsFile = $fileDirname.'/'.$pathInfo['filename'].'.m3u8';
            $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
            ->open($initHlsFile);
            $video->exportForHLS()
                ->withEncryptionKey($encryptKey)
                ->setSegmentLength(1)//默认值是10
                ->toDisk("local")
                ->addFormat($format)
                ->save($initHlsFile);
            $this->generatePreview($item->url);
            $this->info('######视频ID：'.$item->id.'执行成功######');
        }

        $this->info('######执行成功######');
        return 0;
    }

}
