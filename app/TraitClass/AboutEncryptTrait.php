<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait AboutEncryptTrait
{
    public function transferImgOut($domain,$img,$_v,$fixType='jpg'): string
    {
        $fileInfo = pathinfo($img);
        if($fixType == 'auto'){
            $image_info = getimagesize($domain . $img);
            $fixType = $image_info['mime'];
        }
        return $domain . $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm?ext='.$fixType.'&_v='.$_v;
    }

    public function syncUpload($img)
    {
        $abPath = public_path().$img;
        if(file_exists($abPath) && is_file($abPath)){
            $content = file_get_contents($abPath);
            $put = Storage::disk('sftp')->put($img,$content);
            //加密
            if($put){
                $fileInfo = pathinfo($img);
                $encryptFile = str_replace('/storage','/public',$fileInfo['dirname']).'/'.$fileInfo['filename'].'.htm';
                $r = Storage::disk('sftp')->put($encryptFile,$content);
                Log::info('==encryptImg==',[$encryptFile,$r]);
            }
        }
    }
}