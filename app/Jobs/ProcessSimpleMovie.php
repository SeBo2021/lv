<?php

namespace App\Jobs;

use AetherUpload\Util;
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

class ProcessSimpleMovie implements ShouldQueue
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

    public string $originName;

    public string $coverImage;

    public string $originVideo;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row)
    {
        // 初始化数据
        $this->row = $row;
        $this->originName = $this->getOriginNameByJson();
        $this->mp4Path = $this->getLocalMp4ByJson();
    }

    private function getOriginNameByJson()
    {
        $raw = json_decode($this->row->video);
        return $raw[0] ?? '';
    }

    private function getLocalMp4ByJson()
    {
        $raw = json_decode($this->row->video);
        $resource = Util::getResource($raw[0] ?? '');
        return $resource->path;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws FileNotFoundException
     */
    public function handle()
    {
        // 截图第一帧
        $this->capture();
        // 上传截图
        $this->syncUpload($this->coverImage);
        // 上传视频
        $this->syncMp4($this->mp4Path);
    }

    public function syncMp4($file)
    {
        $content = Storage::get($file);;
        return Storage::disk('sftp')->put($file, $content);
    }

    /**
     * @throws \Exception
     */
    public function capture()
    {
        $path = pathinfo($this->mp4Path, PATHINFO_FILENAME);
        $file_name = $this->mp4Path;
        $subDir = env('SLICE_DIR', '/slice');
        $sliceDir = 'public' . $subDir;
        $tmp_path = $sliceDir . '/capture/' . $path . '/';
        $dirname = storage_path('app/') . $tmp_path;
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        $format = new \FFMpeg\Format\Video\X264();
        $format->setAdditionalParameters(['-vcodec', 'copy', '-acodec', 'copy']); //跳过编码
        //$format = $format->setAdditionalParameters(['-hwaccels', 'cuda']);//GPU高效转码
        $file_name_name = $file_name;
        $model = \ProtoneMedia\LaravelFFMpeg\Support\FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($file_name_name);
        $video = $model->export()
            ->toDisk("local")
            ->inFormat($format);
        //done 生成截图
        $frame = $video->frame(TimeCode::fromSeconds(1));
        $pathInfo = pathinfo($this->originName, PATHINFO_FILENAME);
        $secondDirAndName = '/capture/' . $pathInfo . '.jpg';
        $cover_path = $sliceDir . $secondDirAndName;
        $this->coverImage = $subDir . $secondDirAndName;
        $frame->save($cover_path);
    }
}
