# AwakeDrive Driver Monitoring System

A Laravel 12 + Filament 3 driver monitoring system for tracking microsleep events and fleet health via MQTT telemetry.

## Stack

- Laravel 12
- Filament 3
- Spatie Laravel Permission
- Laravel Reverb
- php-mqtt/laravel-client
- MySQL or SQLite-ready schema
- Pest for tests

## Installation

1. Copy `.env.example` to `.env` and set your local values.
2. Install PHP dependencies:

   composer install

3. Install frontend dependencies:

   npm install

4. Generate the app key:

   php artisan key:generate

5. Run migrations and seed demo data:

   php artisan migrate --seed

6. Build the frontend assets:

   npm run build

7. Start the queue worker:

   php artisan queue:work

8. Start the Reverb WebSocket server:

   php artisan reverb:start --host=127.0.0.1 --port=8080

   If port 8080 is already in use, change `REVERB_PORT` and `VITE_REVERB_PORT` to another free port and use the same value in both places.

9. Start the MQTT listener:

   php artisan mqtt:listen

10. Access the admin panel at `/admin` and the tenant app at `/app`.

## Demo credentials

- Super admin: `mustofaahmad@poltera.ac.id` / `ZXCasd123!@#`
- Admin: `admin@awakedrive.test` / `password`
- Company admin: `admin0@{companyid}.test` / `password`
- Company operator: `operator0@{companyid}.test` / `password`

## Environment variables

Set the following variables in `.env`:

- `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `BROADCAST_CONNECTION`
- `MQTT_HOST`, `MQTT_PORT`, `MQTT_CLIENT_ID`, `MQTT_AUTH_USERNAME`, `MQTT_AUTH_PASSWORD`
- `MQTT_ENABLE_LOGGING`, `MQTT_LOG_CHANNEL`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
- `DEVICE_OFFLINE_TIMEOUT_MINUTES`

## MQTT demo publish example

```bash
mosquitto_pub -h 127.0.0.1 -p 1883 -t "devices/DMS-0042/telemetry" -m '{"device_id":"DMS-0042","driver_status":"microsleep","latitude":-6.914744,"longitude":107.609810,"timestamp":"2026-08-29T14:32:05Z"}'
```

You can also publish a normal status test:

```bash
php artisan mqtt:publish-demo --device=DMS-0042 --status=normal
```

## Supervisor sample

The sample config is included at `supervisord.awakedrive.conf`.

```ini
[program:awakedrive-mqtt]
command=/usr/bin/php /path/to/awakedrive/artisan mqtt:listen
directory=/path/to/awakedrive
autostart=true
autorestart=true
stderr_logfile=/var/log/awakedrive/mqtt.err.log
stdout_logfile=/var/log/awakedrive/mqtt.out.log

[program:awakedrive-queue]
command=/usr/bin/php /path/to/awakedrive/artisan queue:work --tries=3 --sleep=5 --timeout=0
directory=/path/to/awakedrive
autostart=true
autorestart=true
stderr_logfile=/var/log/awakedrive/queue.err.log
stdout_logfile=/var/log/awakedrive/queue.out.log

[program:awakedrive-reverb]
command=/usr/bin/php /path/to/awakedrive/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/path/to/awakedrive
autostart=true
autorestart=true
stderr_logfile=/var/log/awakedrive/reverb.err.log
stdout_logfile=/var/log/awakedrive/reverb.out.log
```

## Data retention notes

`device_telemetry` grows quickly. Use a retention strategy such as an archival job by month or a cleanup cron that moves old data into a warehouse table.

For offline detection, the scheduler runs:

```bash
php artisan schedule:run
```

and the recurring job is configured in `app/Console/Kernel.php` to mark devices offline after the configured timeout.

## Tests

```bash
php artisan test
```
