<?php

namespace App\Console\Commands;

use App\TraitClass\VideoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class encryptVideoCoverImg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:coverImg';

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
        $table = 'video';
        $items = DB::table($table)->whereIn('id',[7261])->get(['id','cover_img','sync']);
        $domain = env('RESOURCE_DOMAIN');
        foreach ($items as $item){
            $imgUrl = $domain.$item->cover_img;
            $content = $this->getImgBlockData($imgUrl);
            $fileInfo = pathinfo($imgUrl);
            $encryptFile = $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm';
            Storage::disk('sftp')->put($encryptFile,$content);
        }
        $this->info('######轮播图状态更新成功######');
        return 0;
    }

    public function getImgBlockData($url)
    {
        $ch = curl_init($url);
        // 超时设置
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        // 取前面 168 个字符 通过四张测试图读取宽高结果都没有问题,若获取不到数据可适当加大数值
        curl_setopt($ch, CURLOPT_RANGE, '0-1024000');
        // 跟踪301跳转
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        // 返回结果
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $dataBlock = curl_exec($ch);
        //echo $dataBlock;
        curl_close($ch);
        return $dataBlock ?? '';
    }
}
