<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    protected $table = 'prompts';
    protected $fillable = ['type', 'name', 'prompt'];
}
