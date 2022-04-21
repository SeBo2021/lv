<?php

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\User;
use App\TraitClass\ChannelTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\StatisticTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use phpseclib\Crypt\Random;
use Zhuzhichao\IpLocationZh\Ip;
use Illuminate\Support\Str;

class ProcessLogin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StatisticTrait, PHPRedisTrait, ChannelTrait;

    /**
     * 任务尝试次数
     *
     * @var int
     */
    public int $tries = 1;

    //跳跃式延迟执行
    //public $backoff = [60,180];

    public array $loginLogData=[];

    public $code = '';

    public int $device_system = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($loginLogData)
    {
        //
        $this->code = $loginLogData['promotion_code'];
        $this->device_system = (int)$loginLogData['device_system'];
        unset($loginLogData['promotion_code']);
        $this->loginLogData = $loginLogData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //增加登录次数
        $uid = $this->loginLogData['uid'];
        // 冗余最后一次登录地理信息
        $area = Ip::find($this->loginLogData['ip']);
        $areaJson = json_encode($area,JSON_UNESCAPED_UNICODE);

        if($this->loginLogData['type']==1){
            $updateData = $this->bindChannel();
            $this->saveStatisticByDay('install',$updateData['channel_id'],$this->device_system);
            $this->saveStatisticByDay('active_users',$updateData['channel_id'],$this->device_system);
        }else{
            $this->saveStatisticByDay('login_number',$this->loginLogData['channel_id'],$this->device_system);
            $this->saveStatisticByDay('active_users',$this->loginLogData['channel_id'],$this->device_system);
        }
        
        //记录登录日志
        $this->loginLogData['area'] = $areaJson;
        if(isset($this->loginLogData['clipboard'])){
            unset($this->loginLogData['clipboard']);
        }
        LoginLog::query()->create($this->loginLogData);
        $updateData['location_name'] = $areaJson;
        if(!$this->code){
            $invitationCode = Str::random(2).$uid.Str::random(2);
            $updateData['promotion_code'] = $invitationCode;
        }
        DB::table('users')->where('id',$uid)->increment('login_numbers',1,$updateData);
    }

    public function bindChannel()
    {
        //绑定渠道推广
        //$lastTime = strtotime('-1 day');
        $lastTime = strtotime('-2 hour');
        $device_system = $this->loginLogData['device_system'];
        $channel_id = 0;
        $clipboard = $this->loginLogData['clipboard'] ?? '';
        if(!empty($clipboard)){
            $channel_id = $this->getChannelIdByPromotionCode($this->loginLogData['clipboard']);
            $channel_pid = $this->getChannelInfoById($channel_id)->pid ?? 0;
            //Log::info('==BindChannelUserClipboard==',[$clipboard,$channel_id]);
        }else{
            $downloadInfoArr = $this->redis()->lRange($this->apiRedisKey['app_download'],0,-1);
            $downloadInfo = !empty($downloadInfoArr) ? $downloadInfoArr : [];

            foreach ($downloadInfo as $item)
            {
                if(!empty($downloadInfoArr)){
                    $tmpItem = $item;
                    $item = unserialize($item);
                    $downLoadTime = strtotime($item['created_at']);
                    if($downLoadTime < $lastTime){
                        $this->redis()->lRem($this->apiRedisKey['app_download'],$tmpItem,1);
                    }
                }else{
                    $item = (array)$item ;
                }
                if($this->loginLogData['ip'] == $item['ip']){
                    //$pid = DB::table('users')->where('promotion_code',$item['code'])->value('id');
                    $pid = 0;
                    $channel_id = $item['channel_id'];
                    $device_system = $item['device_system'];
                    $channel_pid = DB::table('channels')->where('id',$channel_id)->value('pid');
                    $this->device_system = $item['device_system'];
                    break;
                }
            }
        }

        //Log::info('==BindChannelUser==',$updateData);
        return [
            'pid'=>$pid ?? 0,
            'channel_id'=>$channel_id ?? 0,
            'device_system'=>$device_system ?? 0,
            'channel_pid'=>$channel_pid ?? 0
        ];
    }
}
