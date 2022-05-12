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

class ProcessSyncMiddleSectionTable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, VideoTrait, PHPRedisTrait,CommTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->syncMiddleSectionTable();
        $this->resetHomeRedisData();
    }
}
