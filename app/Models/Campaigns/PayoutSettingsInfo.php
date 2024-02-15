<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutSettingsInfo extends Model
{
    use HasFactory;
    protected $table = 'payout_settings_info';
    protected $connection = 'kindgiving_database';
    protected $fillable = [
        'user_id',
        'payout_method',
        'account_name',
        'account_number',
        'bank_name'
    ];
}
