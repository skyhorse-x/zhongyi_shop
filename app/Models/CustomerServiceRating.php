<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerServiceRating extends Model
{
    protected $fillable = [
        'session_no', 'user_id', 'admin_id', 'score', 'attitude', 'solved',
        'comment', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'score' => 'integer',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function admin() { return $this->belongsTo(Admin::class); }
    public function session() { return $this->belongsTo(CustomerServiceSession::class, 'session_no', 'session_no'); }
}
