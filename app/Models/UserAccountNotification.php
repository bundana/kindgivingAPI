<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccountNotification extends Model
{
    use HasFactory; 
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message'
    ];
}
