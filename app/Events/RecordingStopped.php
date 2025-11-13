<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RecordingStopped implements ShouldBroadcast
{
    use InteractsWithSockets;

    public $channel;

    public function __construct($channel)
    {
        $this->channel = $channel;
    }

    public function broadcastOn()
    {
        return ['recording'];
    }

    public function broadcastAs()
    {
        return 'RecordingStopped';
    }
}