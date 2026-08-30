<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelemetryIngestionService;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Listen for telemetry from edge devices on MQTT topics.';

    public function handle(TelemetryIngestionService $service): int
    {
        $mqtt = MQTT::connection();

        $handler = function (string $topic, string $message) use ($service): void {
            $payload = json_decode($message, true);

            if (! is_array($payload)) {
                $this->warn('Malformed MQTT payload received on '.$topic);

                return;
            }

            $service->processPayload($payload);
        };

        $mqtt->subscribe('awake-drive/+/logs', $handler);

        $this->info('MQTT listener active. Waiting for telemetry...');
        $mqtt->loop(true);

        return self::SUCCESS;
    }
}
