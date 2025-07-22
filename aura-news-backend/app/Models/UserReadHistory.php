<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 
class UserReadHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'article_id', 'read_at', 'ip', 'session_id'];
} 