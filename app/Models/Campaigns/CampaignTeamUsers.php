<?php

namespace App\Models\Campaigns;

use App\Models\Campaigns\Donations;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignTeamUsers extends Model
{
 use HasFactory;
 // protected $table = 'campaign_agents';
 protected $connection = 'kindgiving_database';
 protected $fillable = ['creator', 'user_id'];

 public function user()
 {
  return $this->belongsTo(User::class, 'user_id', 'user_id');
 }

 public function donations()
 {
  return $this->hasMany(Donations::class, 'agent_id', 'user_id');
 }

 public function campaign()
 {
     return $this->belongsTo(Campaign::class, 'campaign_id', 'campaign_id');
 }

 public function creator()
 {
     return $this->belongsTo(User::class, 'creator', 'user_id');
 }
}
