<?php

namespace App\Jobs;

use App\TraitClass\CommTrait;
use App\TraitClass\PHPRedisTrait;
use App\TraitClass\VideoTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSyncMiddleTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait, PHPRedisTrait,CommTrait;

    public $flag;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($flag)
    {
        $this->flag = $flag;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //同步版块中间表
        if ($this->flag == 'video') {
            $this->syncMiddleSectionTable();
            //同步标签中间表
            $this->syncMiddleTagTable();
            //清除缓存
            $this->resetHomeRedisData();
        }

    }
}
