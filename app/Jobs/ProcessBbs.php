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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class ProcessBbs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait;

    public int $timeout = 180000; //默认60秒超时


    public string $mp4Path;

    public string $originName;

    public string $coverImage;

    public array $thumbsImage;

    public string $uniImgPath;

    public string $uniVideoPath;

    public int $isThumbs;
    public int $isVideo;

    /**
     * Create a new job instance.
     *
     * @param $row
     * @param int $isThumbs 是否处理相册
     * @param int $isVideo 是否处理视频
     */
    public function __construct($row,$isThumbs = 0,$isVideo = 0)
    {
        $this->row = $row;
        $date = date('Ymd');
        $this->uniImgPath = sprintf("/upload/images/%s/", $date);
        $this->uniVideoPath = sprintf("/upload/video/%s/", $date);
        $this->isThumbs = $isThumbs;
        $this->isVideo = $isVideo;
        // 初始化数据
        $this->originName = $this->getOriginNameByJson();
        $this->mp4Path = $this->getLocalMp4ByJson();
        $this->thumbsImage = $this->getThumbsByJson();
    }

    /**
     * 得到原始文件名
     * @return mixed
     */
    private function getOriginNameByJson(): mixed
    {
        $raw = json_decode($this->row->video, true);
        return $raw[0] ?? '';
    }

    /**
     * 得到封面json
     * @return mixed
     */
    private function getThumbsByJson(): mixed
    {
        return json_decode($this->row->thumbs, true);
    }

    /**
     * 取出json格式中mp4格式
     * @return string
     */
    private function getLocalMp4ByJson(): string
    {
        $raw = json_decode($this->row->video, true);
        if ($raw[0] ?? false) {
            $resource = Util::getResource($raw[0]);
            return $resource->path;
        }
        return '';
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        // 上传图片
        if (($this->isThumbs) && ($this->thumbsImage)) {
            $this->syncThumbs();
        }
        // 截图第一帧
        if (($this->isVideo) && ($this->mp4Path)) {
            $cover = $this->capture();
            $this->syncCover($cover);
            //切片
            $videoName = $this->uniVideoPath . $this->originName;
            $this->comHlsSlice($videoName,$this->mp4Path);
            $this->comSyncSlice($videoName,true);
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
        //
        $fileInfo = pathinfo($coverName);
        $encryptFile = str_replace('/storage','/public',$fileInfo['dirname']).'/'.$fileInfo['filename'].'.htm';
        $r = Storage::disk('sftp')->put($encryptFile,$content);
        Log::info('==encryptImg==',[$encryptFile,$r]);
        if ($result) {
            DB::table('community_bbs')->where('id', $this->row->id)->update([
                'video_picture' => json_encode([$coverName])
            ]);
        }
    }

    /**
     * 上传相册
     */
    public function syncThumbs()
    {
        foreach ($this->thumbsImage as $pic) {
            $file = '/' . public_path() . $pic;
            $exist = Storage::disk('sftp')->exists($pic);
            if ($exist) {
                continue;
            }
            $content = file_get_contents($file);
            $upload = Storage::disk('sftp')->put($pic, $content);
            if ($upload) {
                Storage::delete($this->mp4Path);
            }
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
        DB::table('community_bbs')->where('id', $this->row->id)->update([
            'sync' => 1,
            'video' => json_encode([$videoName])
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
        $subDir = env('SLICE_DIR', '/slice');
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
        $secondDirAndName = '/' . $pathInfo . '.jpg';
        $cover_path = $secondDirAndName;
        $this->coverImage = $pathInfo . '.jpg';
        $frame->save($cover_path);
        return $subDir . $secondDirAndName;
    }
}
