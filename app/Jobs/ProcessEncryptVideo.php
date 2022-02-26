<?php

namespace App\Jobs;

use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class ProcessEncryptVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait, PHPRedisTrait;

    public $item;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($item)
    {
        //
        $this->item = $item;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $pathInfo = pathinfo($this->item->hls_url);
        $fileDirname = str_replace('/storage','/public',$pathInfo['dirname']);
        /*$previewFile = $fileDirname.'/preview.m3u8';
        if(Storage::disk('sftp')->exists($previewFile)){
            Storage::disk('sftp')->delete($previewFile);
        }*/
        //下载到本地
        $allFiles = Storage::disk('sftp')->files($fileDirname);
        foreach ($allFiles as $hlsFile){
            $content = Storage::disk('sftp')->get($hlsFile);
            Storage::disk('local')->put($hlsFile,$content);
            //$this->info($hlsFile);
        }
        //切片加密
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

        //上传到远程
        $localAllFiles = Storage::disk('local')->files($fileDirname);
        foreach ($localAllFiles as $localFile){
            $content = Storage::disk('local')->get($localFile);
            Storage::disk('sftp')->put($localFile,$content);
        }
        //重新生成预览
        $this->generatePreview($this->item->url);
        //删除本地切片目录
        Storage::disk('local')->deleteDirectory($fileDirname);
    }
}
