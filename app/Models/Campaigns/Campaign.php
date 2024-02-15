<?php

namespace App\Models\Campaigns;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;
    protected $table = 'campaigns';
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'manager_id',
        'campaign_id',
        'name',
        'category',
        'description',
        'target',
        'image',
        'slug',
        'status',
        'agent_commission',
        'end_date',
        'hide_target',
        'hide_raised',
        'visibility',
    ];


    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
