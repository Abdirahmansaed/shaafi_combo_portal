<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriberAction extends Model
{
    use HasFactory;

    protected $connection = 'mysql_portal';

    protected $fillable = ['purchase_id', 'msisdn', 'agent_status', 'done_by', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
