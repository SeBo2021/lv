<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait StatisticTrait
{
    public function getDateArr($t=null): array
    {
        $time = $t ?? time();
        $dateArr['at'] = date('Y-m-d H:i:s',$time);
        $dateArr['time'] = $time;
        $dateArr['day'] = date('Y-m-d',$time);
        $dateArr['day_time'] = strtotime($dateArr['day']);
        return $dateArr;
    }

    public function saveStatisticByDay($field,$channel_id,$device_system,$date=null)
    {
        $dateArr = $date ?? $this->getDateArr();
        $queryBuild = DB::table('statistic_day')
            ->where('channel_id',$channel_id)
            ->where('device_system',$device_system)
            ->where('at_time',$dateArr['day_time']);
        $one = $queryBuild->first(['id',$field]);
        //流量型统计
        if(!$one){
            $insertData = [
                'channel_id' => $channel_id,
                $field => 1,
                'device_system' => $device_system,
                'at_time' => $dateArr['day_time'],
            ];
            DB::table('statistic_day')->insert($insertData);
        }else{
            $queryBuild->increment($field);
        }

        //总统计
        $channelInfo = DB::table('channels')->find($channel_id);
        if($channelInfo){
            $statisticTable = 'channel_day_statistics';
            $hasStatistic = DB::table($statisticTable)->where('channel_id',$channel_id)->where('date_at',date('Y-m-d',$dateArr['day_time']))->first(['id',$field]);
            if(!$hasStatistic){
                $insertDeductionData = [];
                $insertDeductionData[$field] = 1;
                $insertDeductionData['date_at'] = date('Y-m-d',$dateArr['day_time']);
                $insertDeductionData['channel_id'] = $channel_id;
                $insertDeductionData['channel_pid'] = $channelInfo->pid;
                $insertDeductionData['channel_status'] = $channelInfo->status;
                $insertDeductionData['channel_name'] = $channelInfo->name;
                $insertDeductionData['channel_promotion_code'] = $channelInfo->promotion_code;
                $insertDeductionData['channel_code'] = $channelInfo->number;
                $insertDeductionData['channel_type'] = $channelInfo->type;
                $insertDeductionData['unit_price'] = $channelInfo->unit_price;
                $insertDeductionData['share_ratio'] = $channelInfo->share_ratio ?? 0;
                //增加真实安装量
                if($field == 'install'){
                    $insertDeductionData['install'] = 100;
                    $insertDeductionData['install_real'] = 1;
                }
                DB::table($statisticTable)->insert($insertDeductionData);
            }else{
                //更新扣量表
                if($field == 'install'){
                    $stepValue = 100;
                    if(($channelInfo->type<3) && ($channel_id>0)){ //cpa、cps、包月都支持扣量
                        $is_deduction = $channelInfo->is_deduction;
                        $deductionValue = $channelInfo->deduction;
                        //是否开启前十个下载扣量
                        if($is_deduction == 1){ //开启
                            $sumHits = DB::table($statisticTable)->where('channel_id',$channel_id)->sum('hits');
                            if(($sumHits/100) < 11){ //第一次前十个
                                $stepValue = 0;
                            }
                        }else{ //关闭
                            $stepValue = round(1*(1-$deductionValue/10000),2) * 100;
                            DB::table('channels')->where('id',$channel_id)->update(['is_deduction'=>0]);
                        }
                    }
                    if($stepValue>0){
                        DB::table($statisticTable)
                            ->where('channel_id',$channel_id)
                            ->where('date_at',date('Y-m-d',$dateArr['day_time']))
                            ->increment('install',$stepValue);
                    }

                    DB::table($statisticTable)
                        ->where('channel_id',$channel_id)
                        ->where('date_at',date('Y-m-d',$dateArr['day_time']))
                        ->increment('install_real');
                }else{
                    DB::table($statisticTable)
                        ->where('channel_id',$channel_id)
                        ->where('date_at',date('Y-m-d',$dateArr['day_time']))
                        ->increment($field);
                }
            }
        }

    }

    //保存活跃用户数据
    public function saveUsersDay($uid,$channel_id,$device_system)
    {
        $at_time = strtotime(date('Y-m-d'));
        //
        $userHadCome = DB::table('users_day')->where('uid',$uid)->where('at_time',$at_time)->first(['id','uid']);
        //Log::debug('saveUsersDay===user_come:',[$userHadCome]);
        if(!$userHadCome){
            DB::table('users_day')->insert([
                'uid' => $uid,
                'at_time' => $at_time,
                'channel_id' => $channel_id,
                'device_system' => $device_system,
            ]);
            $this->saveStatisticByDay('active_users',$channel_id,$device_system);
        }


    }

    /**
     * 通用用户表修复统计数据
     * @param $channelId 渠道id
     * @param $deviceSystem 设备类型
     * @param $timeRange 时间区域
     * @param $startDate 开始时间
     * @param $endDate 结束时间
     * @return array 返回值
     */
    public function fixDataByUserTable($channelId, $deviceSystem, $timeRange, $startDate, $endDate,$isGroup = false)
    {
        $userModel = DB::table('users')
            ->where(function ($query) use($channelId,$deviceSystem,$timeRange,$startDate,$endDate){
                if($channelId!==null){
                    $query->where('channel_id',$channelId);
                }

                if( $deviceSystem>0 ){
                    $query->where('device_system',$deviceSystem);
                }

                if($timeRange != 0){
                    $query->whereBetween('created_at',[$startDate,$endDate]);
                }
            });
        if ($isGroup) {
            $totalUesrData = $userModel->select('device_system',DB::raw('count(1) as value'))
                ->where('device_system','>',0)
                ->groupBy(['device_system'])->get();
            return $totalUesrData;
        } else {
            $totalUesrData = $userModel->select(DB::raw('count(1) as install'),DB::raw('sum( CASE WHEN phone_number = "0" THEN 0 ELSE 1 END ) AS register'))->first();
        }
        $newInstall = $totalUesrData->install??0;
        $newRegister = $totalUesrData->register??0;
        return [
            'newInstall' => $newInstall,
            'newRegister' => $newRegister,
        ];
    }

}