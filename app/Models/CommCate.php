<?php


namespace App\Models;

class CommCate extends BaseModel
{
    protected $table = 'community_cate';

    public function up(){
        return $this->hasOne(CommCate::class,'id','parent_id');
    }

}
