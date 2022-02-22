<?php

namespace App\Console\Commands;

use App\TraitClass\VideoTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class encryptShortVideoCoverImg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'encrypt:shortCoverImg';

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
        $table = 'video_short';
        $items = DB::table($table)
            //->whereIn('id',['7261','7260'])
            ->get(['id','cover_img']);
        $domain =str_replace('https','http',env('RESOURCE_DOMAIN'));
        end($domain);
        $last = key($items);
        foreach ($items as $index => $item){
            $imgUrl = $domain.$item->cover_img;
            //$content = $this->getImgBlockData($imgUrl);
            $ch = curl_init($imgUrl);
            // 超时设置
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            // 取前面 168 个字符 通过四张测试图读取宽高结果都没有问题,若获取不到数据可适当加大数值
            curl_setopt($ch, CURLOPT_RANGE, '0-1024000');
            // 跟踪301跳转
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            // 返回结果
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $dataBlock = curl_exec($ch);
            if($last==$index){
                curl_close($ch);
            }
            $fileInfo = pathinfo($item->cover_img);
            $encryptFile = $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm';
            //Log::info('===encryptImg===',[$encryptFile,$content]);
            $bool = Storage::disk('sftp')->put($encryptFile,$dataBlock);
            if($bool==true){
                $this->info('######视频ID:'.$item->id.' 封面图加密成功######');
            }
        }
        $this->info('######视频封面图加密成功######');
        return 0;
    }

    /*public function getImgBlockData($url)
    {
        $ch = curl_init($url);
        // 超时设置
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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
    }*/
}
