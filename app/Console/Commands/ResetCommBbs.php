<?php

namespace App\Console\Commands;

use App\Models\CommBbs;
use App\TraitClass\PHPRedisTrait;
use AWS\CRT\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetCommBbs extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset_community_bbs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return int
     */
    public function handle(): int
    {
        $paramTableName = 'community_bbs';
        $Items = DB::table($paramTableName)->get(['id']);
        $bar = $this->output->createProgressBar(count($Items));
        $redis = $this->redis();
        $bar->start();
        foreach ($Items as $model)
        {
            $list = CommBbs::query()
                ->leftJoin('users', 'community_bbs.author_id', '=', 'users.id')
                ->select('community_bbs.id', 'content', 'thumbs', 'likes', 'comments', 'rewards', 'users.location_name', 'community_bbs.updated_at', 'nickname', 'sex', 'is_office', 'video', 'users.id as uid', 'users.avatar', 'users.level', 'users.vip as vipLevel')
                ->where('community_bbs.id', $model->id)
                ->orderBy('updated_at', 'desc')->get()->toArray();
            $listKey = 'communityBbsList:'.$model->id;
            $redis->set($listKey,json_encode($list,JSON_UNESCAPED_UNICODE));
            $bar->advance();
        }
        $bar->finish();
        $this->info('######执行成功######');
        return 0;
    }
}
