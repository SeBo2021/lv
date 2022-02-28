<?php

namespace App\TraitClass;

use App\Models\CommCate;

trait CommTrait
{
    use PHPRedisTrait;
    public function getCommCate()
    {
        $raw = $this->redis()->get('common_cate');
        $data = @json_decode($raw, true);
        $data = $data ?? [];
        if(!empty($data)){
            return $data;
        }
        //$data = [];
        $raw = CommCate::query()->orderBy('order', 'desc')
            ->select('id','name','parent_id','mark','order','is_allow_post','can_select_city')
            ->get()->toArray();
        foreach ($raw as $v1) {
            $this->redis()->hSet('common_cate_help', "c_{$v1['id']}", $v1['mark']);
            if ($v1['parent_id'] == 0) {
                $data[] = $v1;
            };
        }
        foreach ($raw as $v2) {
            if ($v2['parent_id'] > 0) {
                $this->redis()->hSet('common_cate_help', "c_{$v2['id']}", $v2['mark']);
                foreach ($data as $k3=>$v3) {
                    if ($v2['parent_id'] == $v3['id']) {
                        $data[$k3]['childs'][] = $v2;
                    }
                }
            }
        }
        $this->redis()->set('common_cate',json_encode($data));
        return $data;
    }
}