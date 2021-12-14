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

        $preCheck = $_FILES[$files];
        if (is_array($preCheck['name'] ?? '')) {
            $result = [];
            foreach ($preCheck['name'] as $k => $item) {
                $data = [
                    'name' => $preCheck['name'][$k],
                    'type' => $preCheck['type'][$k],
                    'tmp_name' => $preCheck['tmp_name'][$k],
                    'error' => $preCheck['error'][$k],
                    'size' => $preCheck['size'][$k],
                ];
                $result[] = UploadFile::upload($files, $file_type, $method, $group_id, [], $oss_type, admin('id'),'admin',$data);
            }
        } else {
            $result = UploadFile::upload($files, $file_type, $method, $group_id, [], $oss_type, admin('id'));
        }

        return $result;

    }
}