<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class USSDSESSION extends Model
{
    use HasFactory;
    public $table = 'ussd_donations_sessions';
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'campaign_id',
        'session_id',
        'mobile',
        'platform',
        'message',
        'service_code',
        'operator',
        'donation_amount',
        'type'
    ];
}
