<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteRegistration extends Model
{
    protected $fillable = [
        'inviter_user_id',
        'promoter_id',
        'user_id',
        'invite_code',
        'ip',
        'user_agent',
        'device_type',
        'device_model',
        'browser',
        'os',
        'fingerprint',
        'is_fraud',
        'fraud_reason',
        'risk_score',
    ];

    protected $casts = [
        'is_fraud'    => 'boolean',
        'risk_score'  => 'integer',
    ];

    /**
     * 推广员（如果有）
     */
    public function promoter()
    {
        return $this->belongsTo(Promoter::class);
    }

    /**
     * 邀请人用户（任意用户）
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /**
     * 被邀请注册的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
