<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\TraitClass\PHPRedisTrait;

class LiveMergeRedis extends Command
{
    use PHPRedisTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live_merge_redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '移动直播到redis缓存';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(lang('开始执行'));
        $this->processCache(1, 100);
        $this->info(lang('执行成功'));
    }

    public function processCache($page, $perNum)
    {
        try {
            $start = ($page - 1) * $perNum;
            $video = DB::table('live')
                ->select(['id', 'name', 'cid', 'cat','tag', 'restricted', 'sync', 'title', 'url', 'gold', 'duration', 'duration_seconds', 'type',  'views', 'likes', 'comments', 'cover_img', 'updated_at','intro','age', 'hls_url', 'dash_url'])
                ->where('status', 1)
                ->offset($start)->limit($perNum)->get();

            foreach ($video as $item) {
                $mapNum = $item->id % 100;
                $cacheKey = "fake_live_$mapNum";
                $this->redis()->hSet($cacheKey, $item->id, json_encode($item));
            }
            if (count($video) == $perNum) {
                $this->processCache($page+1, $perNum);
            }
        } catch (Exception $e) {
            Log::error('执行过程出错===' . $e->getMessage());
        }

    }
}
