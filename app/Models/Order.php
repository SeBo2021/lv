<?php

namespace App\Models;

class Order extends BaseModel
{
    public function rechargeInfo(){
        return $this->hasOne(Recharge::class,'order_id','id');
    }
}