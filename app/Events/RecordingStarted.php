<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecordingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $channel;
    public $uid;

    /**
     * Create a new event instance.
     *
     * @param string $channel
     * @param string $uid
     */
    public function __construct(string $channel, string $uid)
    {
        $this->channel = $channel;
        $this->uid = $uid;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Use a public channel
        return new Channel('recording');
    }

    /**
     * Optional: customize the broadcast payload
     */
    public function broadcastWith()
    {
        return [
            'channel' => $this->channel,
            'uid' => $this->uid,
            'status' => 'started'
        ];
    }
}
