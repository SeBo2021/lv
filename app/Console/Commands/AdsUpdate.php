<?php

namespace App\Console\Commands;

use App\TraitClass\AdTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdsUpdate extends Command
{
    use AdTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:up';

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
    public function handle()
    {
        $table = 'ads';
        $items = DB::table($table)->get();
        $nowTime = time();
        foreach ($items as $item){
            if($item->start_at && $item->end_at){
                switch ($item->status){
                    case 1:
                        if(strtotime($item->end_at)<=$nowTime){
                            DB::table($table)->where('id',$item->id)->update(['status'=>0]);
                        }
                        break;
                    case 0:
                        if(strtotime($item->start_at)<=$nowTime){
                            DB::table($table)->where('id',$item->id)->update(['status'=>1]);
                        }
                        break;
                }
            }

        }
        if(!empty($this->getConfigDataFromDb())){
            $this->info('######广告状态更新成功######');
        }
        return 0;
    }
}
