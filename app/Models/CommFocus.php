<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommFocus extends BaseModel
{
    protected $table = 'community_focus';
    //
    public function user(){
        return $this->hasOne(User::class,'id','author_id');
    }
}
