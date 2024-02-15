<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
 use HasFactory;
 protected $table = 'campaign_commision';
 protected $connection = 'kindgiving_database';
 protected $fillable = [
  'user_id',
  'campaign_id',
  'commission',
 ];

 public function campaign()
 {
  return $this->hasMany(Campaign::class, 'campaign_id', 'campaign_id');
 }
}
