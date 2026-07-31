<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_no', 'user_id', 'pay_type', 'amount', 'status', 'trade_no', 'pay_response', 'notify_response', 'paid_at'];
}
