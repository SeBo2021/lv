<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait StatisticTrait
{
    use PHPRedisTrait,ChannelTrait;
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
        $redis = $this->redis();
        $statistic_day_key = 'statistic_day:'.$channel_id.':'.$device_system.':'.$dateArr['day_time'];

        if(!$redis->exists($statistic_day_key)){
            $queryBuild = DB::table('statistic_day')
                ->where('channel_id',$channel_id)
                ->where('device_system',$device_system)
                ->where('at_time',$dateArr['day_time']);
            //$one = $queryBuild->first(['id',$field]);
            $one = $queryBuild->first();

            //流量型统计
            if(!$one){
                $insertData = [
                    'channel_id' => $channel_id,
                    $field => 1,
                    'device_system' => $device_system,
                    'at_time' => $dateArr['day_time'],
                ];
                //DB::table('statistic_day')->insert($insertData);
                $redis->hMSet($statistic_day_key,$insertData);
            }else{
                $one = (array)$one;
                $redis->hMSet($statistic_day_key,$one);
            }
            /*else{
                $queryBuild->increment($field);
            }*/
        }else{
            $redis->hIncrBy($statistic_day_key,$field,1);
        }


        //总统计
        //$channelInfo = DB::table('channels')->find($channel_id);
        $channelInfo = $this->getChannelInfoById($channel_id);
        if($channelInfo){
            $statisticTable = 'channel_day_statistics';
            $channel_day_statistics_key = 'channel_day_statistics:'.$channel_id.':'.$dateArr['day'];
            if(!$redis->exists($channel_day_statistics_key)){
                $hasStatistic = DB::table($statisticTable)->where('channel_id',$channel_id)->where('date_at',$dateArr['day'])->first();
                if(!$hasStatistic){
                    $insertDeductionData = [];
                    $insertDeductionData[$field] = 1;
                    $insertDeductionData['date_at'] = $dateArr['day'];
                    $insertDeductionData['channel_id'] = $channel_id;
                    $insertDeductionData['channel_pid'] = $channelInfo->pid;
                    $insertDeductionData['channel_status'] = $channelInfo->status;
                    $insertDeductionData['channel_name'] = $channelInfo->name;
                    $insertDeductionData['channel_promotion_code'] = $channelInfo->promotion_code;
                    $insertDeductionData['channel_code'] = $channelInfo->number;
                    $insertDeductionData['principal'] = $channelInfo->principal;
                    $insertDeductionData['channel_type'] = $channelInfo->type;
                    $insertDeductionData['unit_price'] = $channelInfo->unit_price;
                    $insertDeductionData['share_ratio'] = $channelInfo->share_ratio ?? 0;
                    //增加真实安装量
                    if($field == 'install'){
                        $insertDeductionData['install'] = 100;
                        $insertDeductionData['install_real'] = 1;
                    }
                    $redis->hMSet($channel_day_statistics_key,$insertDeductionData);
                }else{
                    $hasStatistic = (array)$hasStatistic;
                    $redis->hMSet($channel_day_statistics_key,$hasStatistic);
                }
                //DB::table($statisticTable)->insert($insertDeductionData);
            }else{
                if($field == 'install'){
                    $stepValue = 100;
                    if(($channelInfo->type<3) && ($channel_id>0)){ //cpa、cps、包月都支持扣量
                        $is_deduction = $channelInfo->is_deduction;
                        $deductionValue = $channelInfo->deduction;
                        //是否开启前十个下载扣量
                        $stepValue = round(1*(1-$deductionValue/10000),2) * 100;
                        if($is_deduction == 1){ //开启
                            //$install_real = DB::table($statisticTable)->where('channel_id',$channel_id)->where('date_at',date('Y-m-d'))->sum('install_real');
                            $install_real = (int) $redis->hGet(str_replace('laravel_database_','',$channel_day_statistics_key),'install_real');
                            if($install_real < 11){ //第一次前十个
                                $stepValue = 100;
                            }
                        }
                    }
                    if($stepValue>0){
                        $redis->hIncrBy($channel_day_statistics_key,'install',$stepValue);
                    }
                    $redis->hIncrBy($channel_day_statistics_key,'install_real',1);
                }else{
                    $redis->hIncrBy($channel_day_statistics_key,$field,1);
                }
            }

        }

    }

}