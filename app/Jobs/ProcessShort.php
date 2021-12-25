<?php

namespace App\Jobs;

use AetherUpload\Util;
use App\TraitClass\VideoTrait;
use Exception;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class ProcessShort implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait;

    public int $timeout = 180000; //默认60秒超时


    public string $mp4Path;

    public string $originName;

    public string $coverImage;

    public string $uniImgPath;

    public string $uniVideoPath;

    public int $isThumbs;
    public int $isVideo;

    /**
     * Create a new job instance.
     *
     * @param $row
     * @param int $isVideo 是否处理视频
     */
    public function __construct($row, $isVideo = 0)
    {
        $this->row = $row;
        $date = date('Ymd');
        $this->uniImgPath = sprintf("/short/images/%s/", $date);
        $this->uniVideoPath = sprintf("/short/video/%s/", $date);
        $this->isVideo = $isVideo;
        // 初始化数据
        $this->originName = $this->getOriginNameBy();
        $this->mp4Path = $this->getLocalMp4();
    }

    /**
     * 得到原始文件名
     * @return mixed
     */
    private function getOriginNameBy(): mixed
    {
        return $this->row->url;
    }

    /**
     * 取出json格式中mp4格式
     * @return string
     */
    private function getLocalMp4(): string
    {
        $resource = Util::getResource($this->row->url);
        return $resource->path ?? '';
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        // 截图第一帧
        if (($this->isVideo) && ($this->mp4Path)) {
            $cover = $this->capture();
            $this->syncCover($cover);
            // 上传视频
            $this->syncMp4($this->originName);
        }
    }

    /**
     * 同步相册封面
     * @param $img
     */
    public function syncCover($img)
    {
        $coverName = $this->uniVideoPath . $img;
        $content = Storage::get($this->coverImage);
        $result = Storage::disk('sftp')->put($coverName, $content);
        if ($result) {
            DB::table('video_short')->where('id', $this->row->id)->update([
                'cover_img' => $coverName]
            );
        }
    }

    /**
     * 上传mp4原样样式
     * @param $file
     * @return bool
     */
    public function syncMp4($file): bool
    {
        $videoName = $this->uniVideoPath . $file;
        $content = Storage::get($this->mp4Path);
        DB::table('video_short')->where('id', $this->row->id)->update([
            'sync' => 1,
            'url' => $videoName
        ]);
        $exist = Storage::disk('sftp')->exists($videoName);
        if ($exist) {
            // 文件已经上传过
            return true;
        }
        $upload = Storage::disk('sftp')->put($videoName, $content);
        if ($upload) {
            Storage::delete($this->mp4Path);
        }
        return $upload;
    }

    /**
     * 截取视频封面
     * @return string
     * @throws Exception
     */
    public function capture(): string
    {
        $file_name = $this->mp4Path;
        $format = new X264();
        $format->setAdditionalParameters(['-vcodec', 'copy', '-acodec', 'copy']); //跳过编码
        //$format = $format->setAdditionalParameters(['-hwaccels', 'cuda']);//GPU高效转码
        $file_name_name = $file_name;
        $model = FFMpeg::fromDisk("local") //在storage/app的位置
        ->open($file_name_name);
        $video = $model->export()
            ->toDisk("local")
            ->inFormat($format);
        //done 生成截图
        $frame = $video->frame(TimeCode::fromSeconds(1));
        $pathInfo = pathinfo($this->originName, PATHINFO_FILENAME);
        $fileSaveName = $pathInfo . '.jpg';
        // $secondDirAndName = '/' . $fileSaveName;
        // $cover_path = $secondDirAndName;
        $this->coverImage = $fileSaveName;
        $frame->save($fileSaveName);
        return $fileSaveName;
    }
}
