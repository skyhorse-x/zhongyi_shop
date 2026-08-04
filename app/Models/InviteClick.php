<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteClick extends Model
{
    protected $fillable = [
        'inviter_user_id',
        'promoter_id',
        'invite_code',
        'ip',
        'user_agent',
        'device_type',
        'device_model',
        'browser',
        'os',
        'is_duplicate_ip',
        'is_suspicious',
        'fingerprint',
        'clicked_at',
    ];

    protected $casts = [
        'is_duplicate_ip' => 'boolean',
        'is_suspicious'   => 'boolean',
        'clicked_at'      => 'datetime',
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
}
