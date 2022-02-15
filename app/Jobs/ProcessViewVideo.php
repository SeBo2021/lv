<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Video;
use App\TraitClass\StatisticTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessViewVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StatisticTrait;

    public $userModel;

    public $timeout = 5;

    public $video;

    public $tries = 0;

    public $maxExceptions = 1;

    /**
     * 确定任务应该超时的时间
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addSeconds(5);
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userModel, $video)
    {
        $this->userModel = $userModel;
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $vid = $this->video['id'];
        $uid = $this->userModel->id;
        Video::query()->where('id',$vid)->increment('views'); //增加该视频播放次数
        //插入历史记录
        DB::table('view_history')->insertOrIgnore(['uid'=>$uid,'vid'=>$vid]);

        if($this->userModel->long_vedio_times>0){
            //统计激活
            $configData = config_cache('app');
            $setTimes = $configData['free_view_long_video_times'] ?? 0;
            if(($this->userModel->long_vedio_times==$setTimes) && (date('Y-m-d')==date('Y-m-d',strtotime($this->userModel->created_at)))){
                $this->saveStatisticByDay('active_view_users',$this->userModel->channel_id,$this->userModel->device_system);
            }
            //
            User::query()->where('id',$uid)->decrement('long_vedio_times'); //当日观看次数减一
        }
        //
        $this->saveUsersDay($uid, $this->userModel->channel_id, $this->userModel->device_system);
    }
}
