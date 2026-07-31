<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasApiTokens;

    protected $fillable = ['username', 'password', 'name', 'email', 'avatar', 'role_id', 'status', 'last_login_at', 'last_login_ip'];

    protected $hidden = ['password'];
}
