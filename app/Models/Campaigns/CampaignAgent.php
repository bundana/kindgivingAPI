<?php

namespace App\Models\Campaigns;

use App\Models\Campaigns\Donations;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignAgent extends Model
{
    use HasFactory;
    // protected $table = 'campaign_agents';
    protected $connection = 'kindgiving_database';
    protected $fillable = ['campaign_id', 'agent_id', 'name', 'status', 'creator'];

    public function user()
    {
        return $this->belongsTo(User::class, 'agent_id', 'user_id');
    }

    public function campaign()
    {
        return $this->hasMany(Campaign::class, 'campaign_id', 'campaign_id');
    }

    public function donations()
    {
        return $this->hasMany(Donations::class, 'agent_id', 'agent_id');
    }
}
