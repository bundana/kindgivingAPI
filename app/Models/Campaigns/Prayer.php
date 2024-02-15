<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prayer extends Model
{
    use HasFactory;
    protected $table = 'campaign_prayers';
    protected $connection = 'kindgiving_database';
    protected $fillable = ['name', 'email', 'prayer', 'campaign_id'];
}
