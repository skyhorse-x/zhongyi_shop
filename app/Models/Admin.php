<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['username', 'password', 'name', 'email', 'avatar', 'role_id', 'status', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password', 'remember_token'];
}
