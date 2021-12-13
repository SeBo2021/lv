<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommChat extends BaseModel
{
    protected $table = 'community_chat';
    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }

    public function toUser(){
        return $this->hasOne(User::class,'id','to_user_id');
    }
}
