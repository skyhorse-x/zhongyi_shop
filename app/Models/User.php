<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'nickname', 'username', 'mobile', 'email', 'password', 'avatar', 'gender', 'birthday', 'is_promoter', 'parent_id', 'analysis_times', 'status', 'email_verified_at'])]
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
}
