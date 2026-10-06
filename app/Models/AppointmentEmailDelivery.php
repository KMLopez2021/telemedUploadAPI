<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentEmailDelivery extends Model
{
    protected $primaryKey = 'activity_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'activity_id',
        'recipient_email',
        'appointment_data',
        'claim_token',
        'status',
        'sending_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_data' => 'array',
            'sending_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
