<?php

namespace App\Models;

use App\TraitClass\SearchScopeTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class CommReward extends BaseModel
{
    protected $table = 'community_reward';

    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }

    public function bbs(){
        return $this->hasOne(CommBbs::class,'id','bbs_id');
    }

    public function toUser(){
        return $this->hasOne(User::class,'id','to_user_id');
    }

}
