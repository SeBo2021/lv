<?php


namespace App\Models;

class AdminVideo extends BaseModel
{
    protected $table = 'video';

    const UPDATED_AT = NULL;

    public function category(){
        return $this->belongsTo(Category::class,'cid','id');
    }


}
