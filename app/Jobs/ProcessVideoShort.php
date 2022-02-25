<?php

namespace App\Jobs;

use App\TraitClass\VideoTrait;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;

class ProcessVideoShort implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait;

    /**
     * 任务尝试次数
     *
     * @var int
     */
    //public $tries = 3;

    public int $timeout = 180000; //默认60秒超时
    //跳跃式延迟执行
//    public $backoff = [60,180];
    //public $backoff = [18000,36000];


    public string $mp4Path;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        //
        $this->row = $row;
        $this->mp4Path = $this->getMp4FilePath($row->url);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws FileNotFoundException
     * @throws \Exception
     */
    public function handle()
    {
        $file_name = pathinfo($this->row->url,PATHINFO_FILENAME);
        $mp4_path = $this->transcodeMp4($this->mp4Path,$file_name);
        //封面
        $sliceCoverImg = $this->generalCoverImgAtSliceDir($mp4_path);
        $this->syncCoverImg($sliceCoverImg);

        $this->hls_slice($this->row,true);

        //todo 更新状态值表示任务执行完成
        \AetherUpload\Util::deleteResource($this->row->url); //删除对应的资源文件
        \AetherUpload\Util::deleteRedisSavedPath($this->row->url); //删除对应的redis秒传记录
        //同步到资源站
        $this->syncSlice($this->row->url,true);

    }

    public function hls_slice($row, $del=false)
    {
        $mp4_path = $this->getMp4Path();
        $file_name = pathinfo($row->url,PATHINFO_FILENAME);
        //不是mp4格式转mp4
        $mp4_path = $this->transcodeMp4($mp4_path,$file_name);
        //创建对应的切片目录
        $tmp_path = 'public'.env('SLICE_DIR','/slice').'/hls/'.$file_name.'/';
        $dirname = storage_path('app/').$tmp_path;
        if(!is_dir($dirname)){
            mkdir($dirname, 0755, true);
        }

        $m3u8_path = $tmp_path.$file_name.'.m3u8';

        $format = new \FFMpeg\Format\Video\X264();
        //增加commads的参数,使用ffmpeg -hwaccels命令查看支持的硬件加速选项
        $segmentLength = 1;
        $format->setAdditionalParameters([
             '-hls_list_size',0, //设置播放列表保存的最多条目，设置为0会保存有所片信息，默认值为5
            // '-force_key_frames','expr:gte(t,n_forced*'.$segmentLength.')', //强制一秒内必须至少要一帧
            // '-rw_timeout','1800000000',
            // '-listen_timeout','1800000000',
            // '-timeout','1800000000',
            '-vcodec', 'copy','-acodec', 'copy', //跳过编码
        ]);
        //多码率
        //$lowBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(250);
        //$midBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(500);
        //$highBitrate = (new FFMpeg\Format\Video\X264('aac', 'libx264'))->setKiloBitrate(1000);

        $video = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($mp4_path);
        $encryptKey = HLSExporter::generateEncryptionKey();
        Storage::disk('local')->put($tmp_path.'/secret.key',$encryptKey);
        $result = $video->exportForHLS()
            ->withEncryptionKey($encryptKey)
            ->setSegmentLength($segmentLength)//默认值是10
            ->toDisk("local")
            ->addFormat($format)
            //->addFormat($lowBitrate)
            //->addFormat($midBitrate)
            //->addFormat($highBitrate)
            ->save($m3u8_path);
        //同步mp4到资源服
        $content = Storage::get($mp4_path);
        $videoName = sprintf("/short/video/%s/", date('Ymd')) . $this->row->url;
        $upload = Storage::disk('sftp')->put($videoName, $content);
        //删除mp4文件
        if ($upload) {
            Storage::delete($mp4_path);
        }

        $durationSeconds = floor($result->getDurationInMiliseconds()/1000);
        $updateData = ['duration_seconds' => $durationSeconds];
        $updateData['duration'] = $this->formatSeconds($durationSeconds);
        $updateData['url'] = $videoName;
        $updateData['cover_img'] = self::get_slice_url($videoName,'cover');

        DB::table('video_short')->where('id',$this->row->id)->update($updateData);
    }

}
