<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteRegistration extends Model
{
    protected $fillable = [
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

    public function promoter()
    {
        return $this->belongsTo(Promoter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
