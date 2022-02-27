<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait AboutEncryptTrait
{
    public function transferImgOut($img,$domain=null,$_v=null,$fixType='jpg'): string
    {
        $domain = $domain ?? env('RESOURCE_DOMAIN');
        $_v = $_v ?? 1;
        $fileInfo = pathinfo($img);
        if($fixType == 'auto'){
            $image_info = @getimagesize($domain . $img);
            $fixType = $image_info['mime'] ?? 'jpg';
        }
        return $domain . $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm?ext='.$fixType.'&_v='.$_v;
    }

    public function syncUpload($img)
    {
        $img = str_replace('/dash','/coverImg',$img);
        $abPath = public_path().$img;
        if(file_exists($abPath) && is_file($abPath)){
            $content = @file_get_contents($abPath);
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

    public function getOriginEncImg($img,$domain=null): string
    {
        $url = $this->transferImgOut($img, $domain,false,'auto');
        $imgData = @file_get_contents($url);
        parse_str(parse_url($url)['query'],$query_arr);
        $ext = $query_arr['ext'];
        return "data:." . $ext . ";base64," . base64_encode($imgData);
    }
}