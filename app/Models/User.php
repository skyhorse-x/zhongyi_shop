<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'nickname', 'username', 'mobile', 'email', 'password', 'avatar', 'gender', 'birthday', 'is_promoter', 'parent_id', 'analysis_times', 'balance', 'status', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'birthday' => 'date',
            'is_promoter' => 'boolean',
            'status' => 'integer',
        ];
    }

    /**
     * 用户档案
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * 推广员信息
     */
    public function promoter()
    {
        return $this->hasOne(Promoter::class);
    }

    /**
     * 父级用户（推荐人）
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * 下级用户
     */
    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    /**
     * 余额变动流水
     */
    public function balanceLogs()
    {
        return $this->hasMany(UserBalanceLog::class);
    }

    /**
     * 分析次数变动流水
     */
    public function analysisLogs()
    {
        return $this->hasMany(UserAnalysisLog::class);
    }

    /**
     * 注册时赠送基础试用次数
     * 幂等：根据 `user_registered_granted` 字段防止重复赠送
     * 必须在事务内调用
     *
     * @return int 实际赠送的次数（0 表示未赠送或已赠送过）
     */
    public function grantInitialAnalysisTimes(): int
    {
        // 防止重复赠送（同一用户多次注册不会叠加）
        if ($this->user_registered_granted) {
            return 0;
        }

        $defaultTimes = (int) \App\Models\SystemConfig::getValue('user_free_analysis_times', 3);
        if ($defaultTimes <= 0) {
            return 0;
        }

        $before = (int) $this->analysis_times;
        $after  = $before + $defaultTimes;
        $this->analysis_times = $after;
        $this->user_registered_granted = true;
        $this->save();

        // 写审计流水（与已存在模式一致）
        UserAnalysisLog::create([
            'user_id' => $this->id,
            'change'  => $defaultTimes,
            'before'  => $before,
            'after'   => $after,
            'type'    => 'register_grant',
            'remark'  => '注册赠送基础试用次数',
        ]);

        return $defaultTimes;
    }
}
