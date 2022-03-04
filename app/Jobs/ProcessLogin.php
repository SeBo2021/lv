<?php

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\User;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StatisticTrait, PHPRedisTrait;

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
//        User::query()->where('id',$uid)->update(['location_name'=>$areaJson]);
        User::query()->where('id',$uid)->increment('login_numbers',1,['location_name'=>$areaJson]);
        if($this->loginLogData['type']==1){
            $channel_id = $this->bindChannel();
            $this->saveStatisticByDay('install',$channel_id,$this->device_system);
        }
        //记录登录日志
        $this->loginLogData['area'] = $areaJson;
        if(isset($this->loginLogData['clipboard'])){
            unset($this->loginLogData['clipboard']);
        }
        LoginLog::query()->create($this->loginLogData);
        $this->updateUserInfo();
    }

    public function updateUserInfo()
    {
        $uid = $this->loginLogData['uid'];
        //
        if(!$this->code){
            $invitationCode = Str::random(2).$uid.Str::random(2);
            $updateData['promotion_code'] = $invitationCode;
        }
        if(!empty($updateData)){
            User::query()->where('id',$uid)->update($updateData);
        }
    }

    public function bindChannel(): int
    {
        //绑定渠道推广
        $lastTime = strtotime('-1 day');
        $lastDayDate = date('Y-m-d H:i:s',$lastTime);
        $device_system = $this->loginLogData['device_system'];
        $channel_id = 0;
        $clipboard = $this->loginLogData['clipboard'] ?? '';
        if(!empty($clipboard)){
            $channel_id = DB::table('channels')->where('promotion_code',$this->loginLogData['clipboard'])->value('id');
            $channel_pid = DB::table('channels')->where('id',$channel_id)->value('pid');
        }else{
            $downloadInfoArr = $this->redis()->lRange($this->apiRedisKey['app_download'],0,-1);

            if(!empty($downloadInfoArr)){
                $downloadInfo = $downloadInfoArr;
            }else{
                $downloadInfo = DB::connection('master_mysql')->table('app_download')
                    ->where('status',0)
                    ->whereDate('created_at','>=', $lastDayDate)
                    ->orderByDesc('created_at')
                    ->get(['id','channel_id','device_system','ip','agent_info','code','created_at'])->toArray();
            }

            //$nowTime = time();

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
                    $pid = DB::table('users')->where('promotion_code',$item['code'])->value('id');
                    $channel_id = $item['channel_id'];
                    $device_system = $item['device_system'];
                    $channel_pid = DB::table('channels')->where('id',$channel_id)->value('pid');
                    $this->device_system = $item['device_system'];
                    break;
                }
            }
        }

        $updateData = [
            'pid'=>$pid ?? 0,
            'channel_id'=>$channel_id ?? 0,
            'device_system'=>$device_system ?? 0,
            'channel_pid'=>$channel_pid ?? 0
        ];
        $uid = $this->loginLogData['uid'];
        User::query()->where('id',$uid)->update($updateData);
        Log::info('==BindChannelUser==',$updateData);
        return $channel_id;
    }
}
