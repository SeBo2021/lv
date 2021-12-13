<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommBbs extends BaseModel
{
    protected $table = 'community_bbs';
    //
    public function user(){
        return $this->hasOne(User::class,'id','author_id');
    }

    public function category(){
        return $this->hasOne(CommCate::class,'id','category_id');
    }
}
