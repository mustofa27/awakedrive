<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Device;
use App\Models\DeviceTelemetry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceTelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Device $device,
        public ?DeviceTelemetry $telemetry = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.'.$this->device->company_id.'.devices'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.telemetry.updated';
    }
}
