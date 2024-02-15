<?php

namespace App\Models\Campaigns;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutSettlement extends Model
{
    use HasFactory;
    protected $table = 'payout_settlement';
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'settlement_id',
        'reference',
        'user_id',
        'campaign_id',
        'amount',
        'acc_number',
        'acc_name',
        'bank',
        'status'
    ];
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
