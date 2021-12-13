<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommComments extends BaseModel
{
    protected $table = 'community_comments';
    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }

    public function bbs(){
        return $this->hasOne(CommBbs::class,'id','bbs_id');
    }
}
