<?php

namespace App\Models\Campaigns;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundraiserAccount extends Model
{
    use HasFactory;
    protected $table = 'fundraiser_account';
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'user_id',
        'campaign_id',
        'balance',
    ];
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
