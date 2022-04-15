<?php

namespace App\Console\Commands;

use App\TraitClass\PHPRedisTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearRedisKey extends Command
{
    use PHPRedisTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearRedis:keys {key?}';

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
        $paramKey = $this->argument('key');
        if(!$paramKey){
            $this->info('######无匹配的key######');
            return 1;
        }
        $keys = $this->redis()->keys('*'.$paramKey.'*');
        foreach ($keys as $key){
            $this->info('######key:'.$key.'######');
        }
        $this->redisBatchDel($keys);
        $this->info('######执行成功######');
        return 0;
    }
}
