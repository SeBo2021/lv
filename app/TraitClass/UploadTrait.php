<?php

namespace App\TraitClass;

use App\ExtendClass\UploadFile;

trait UploadTrait
{
    public function upFile($request)
    {
        $files = $request->input('files', 'file');
        $file_type = $request->input('file_type', 'image');
        $group_id = $request->input('group_id', '0');
        $method = $request->input('method', 'upload');
        $oss_type = $request->input('oss_type', config('filesystems.default'));
        return UploadFile::upload($files, $file_type, $method, $group_id, [], $oss_type, admin('id'));
    }
}