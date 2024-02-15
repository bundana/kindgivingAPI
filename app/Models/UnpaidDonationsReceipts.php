<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnpaidDonationsReceipts extends Model
{
    use HasFactory;
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'user_id',
        'reference',
        'campaign_id',
        'data',
        'amount',
        'type',
        'phone'
    ];
}
