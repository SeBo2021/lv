<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommMessage extends BaseModel
{
    protected $table = 'community_message';
    //
    public function category(){
        return $this->belongsTo(Category::class,'category_id','id');
    }
}
