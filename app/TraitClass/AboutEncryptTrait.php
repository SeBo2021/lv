<?php

namespace App\TraitClass;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait AboutEncryptTrait
{
    public function transferImgOut($img,$domain=null,$_v=null,$fixType='jpg'): string
    {
        if(!$img){
            return '';
        }
        $domain = $domain ?? env('RESOURCE_DOMAIN');
        $_v = $_v ?? 1;
        $fileInfo = pathinfo($img);
        if(!isset($fileInfo['dirname'])){
            return '';
        }
        if($fixType == 'auto'){
            $image_info = @getimagesize($domain . $img);
            $fixType = $image_info['mime'] ?? 'jpg';
        }
        return $domain . $fileInfo['dirname'].'/'.$fileInfo['filename'].'.htm?ext='.$fixType.'&_v='.$_v;
    }

    public function transferHlsUrl($url,$id=null,$_v=null): string
    {
        $_v = $_v ?? 1;
        $hlsInfo = pathinfo($url);
        if(!isset($hlsInfo['dirname'])){
            return '';
        }
        return $hlsInfo['dirname'].'/'.$hlsInfo['filename'].'.vid?id='.$id.'&_v='.$_v;
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function syncUpload($img)
    {
        $abPath = public_path().$img;
        if((file_exists($abPath) && is_file($abPath)) || Storage::disk('sftp')->exists(str_replace('/storage','/public',$img))){
            $content = @file_get_contents($abPath);
            if(!$content){
                $content = @file_get_contents(VideoTrait::getDomain(env('SFTP_SYNC',1)).$img);
            }
            $put = Storage::disk('sftp')->put($img,$content);
            Storage::disk('sftp1')->put($img,$content);
            //加密
            if($put){
                $fileInfo = pathinfo($img);
                $encryptFile = str_replace('/storage','/public',$fileInfo['dirname']).'/'.$fileInfo['filename'].'.htm';
                $r = Storage::disk('sftp')->put($encryptFile,$content);
                Storage::disk('sftp1')->put($encryptFile,$content);
                Log::info('==encryptImg==',[$encryptFile,$r]);
            }
        }
    }

}