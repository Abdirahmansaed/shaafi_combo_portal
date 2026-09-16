<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActiveSubscriber extends Model
{
    use HasFactory;

    protected $connection = 'mysql_portal';

    protected $fillable = [
        'business_purchase_id',
        'subscriber_number',
        'package_tier',
        'purchase_date',
        'expire_date',
        'action_status',
        'done_by',
        'completed_at',
    ];

    protected $casts = [
        'done_by' => 'integer',
        'purchase_date' => 'datetime',
        'expire_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
