<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class MqttPublishDemo extends Command
{
    protected $signature = 'mqtt:publish-demo {--device=DMS-0042 : Device id} {--status=microsleep : Driver status}';

    protected $description = 'Publish a sample MQTT telemetry payload for testing.';

    public function handle(): int
    {
        $payload = json_encode([
            'device_id' => $this->option('device'),
            'driver_status' => $this->option('status'),
            'latitude' => -6.914744,
            'longitude' => 107.609810,
            'timestamp' => now()->toIso8601String(),
        ]);

        MQTT::publish('devices/'.$this->option('device').'/telemetry', $payload);

        $this->info('Published demo MQTT payload to devices/'.$this->option('device').'/telemetry');

        return self::SUCCESS;
    }
}
