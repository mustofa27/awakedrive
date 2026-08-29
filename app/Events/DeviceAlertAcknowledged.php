<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DeviceAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceAlertAcknowledged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DeviceAlert $alert)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->alert->device->company_id.'.devices'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.alert.acknowledged';
    }
}
