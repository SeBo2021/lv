<?php

namespace App\Models;

class PayLog extends BaseModel
{
    protected $table = 'pay_log';

    public function order(){
        return $this->hasOne(Order::class,'id','order_id');
    }
}