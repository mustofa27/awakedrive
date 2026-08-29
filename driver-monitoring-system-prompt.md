# Build Prompt: Driver Microsleep Monitoring System (Laravel + Filament)

Copy everything below into your AI coding tool (Claude Code, Cursor, etc.) or use it as your own dev spec.

---

## Project Brief

Build a full-stack **Driver Monitoring System** using **Laravel 11** + **Filament 3** that ingests real-time telemetry (microsleep status + GPS location) from edge devices over **MQTT**, and provides a multi-tenant admin panel for companies to manage their fleets and monitor drivers live.

### Core domain

- **Edge devices** detect driver microsleep and publish MQTT messages containing: `device_id`, `driver_status` (e.g. `normal`, `drowsy`, `microsleep`, `offline`), `latitude`, `longitude`, and a `timestamp`.
- **Companies** own multiple **devices**.
- **Company users** log in to see only their own company's devices and drivers.
- **Super admins** manage everything: companies, users, devices, roles, and system settings.

---

## 1. Tech Stack

- Laravel 11 (PHP 8.3+)
- Filament 3.x (admin panel + multi-tenancy)
- MySQL 8 (or PostgreSQL) for relational data
- `php-mqtt/laravel-client` for MQTT subscribe/publish inside Laravel
- Laravel Queues (Redis driver) for processing incoming MQTT messages asynchronously
- Laravel Reverb (or Pusher) + Laravel Echo for real-time dashboard updates (no polling-only fallback)
- `spatie/laravel-permission` for roles & permissions
- `filament/spatie-laravel-permission-plugin` (or `bezhansalleh/filament-shield`) for managing roles/permissions inside Filament
- Leaflet.js (via a Filament/Livewire widget) for the live map — avoid Google Maps to skip billing setup
- Pest for testing
- Laravel Pint for code style (PSR-12)

---

## 2. Data Model

Design normalized migrations and Eloquent models for:

### `companies`
- `id`, `name`, `address`, `phone`, `is_active`, `timestamps`

### `users`
- Standard Laravel fields + `company_id` (nullable — null means super admin / platform staff)
- Roles: `super_admin`, `company_admin`, `company_operator` (via Spatie)

### `devices`
- `id`, `company_id` (FK), `device_uid` (unique string matching MQTT `device_id`), `name`, `driver_name`, `vehicle_plate`, `status` (`active`, `inactive`, `maintenance`), `last_seen_at`, `timestamps`

### `device_telemetry` (or `device_logs`)
- `id`, `device_id` (FK), `driver_status` (enum: `normal`, `drowsy`, `microsleep`, `offline`), `latitude`, `longitude`, `recorded_at`, `timestamps`
- Index on `(device_id, recorded_at)` for fast latest-status and history queries.
- Consider partitioning/archiving strategy since this table grows fast (mention retention/cleanup command).

### `device_alerts`
- `id`, `device_id` (FK), `driver_status`, `latitude`, `longitude`, `triggered_at`, `acknowledged_at` (nullable), `acknowledged_by` (nullable FK to users)
- Created automatically whenever telemetry status is `drowsy` or `microsleep`.

Add model relationships: `Company hasMany Device`, `Company hasMany User`, `Device hasMany DeviceTelemetry`, `Device hasMany DeviceAlert`, `Device belongsTo Company`.

Add a `latestTelemetry()` relationship (`hasOne ... latestOfMany('recorded_at')`) on `Device` for efficient "current status" lookups on the dashboard.

---

## 3. MQTT Ingestion Pipeline

1. Create an Artisan command `mqtt:listen` using `php-mqtt/laravel-client` that subscribes to a wildcard topic, e.g. `devices/+/telemetry`.
2. On each message:
   - Parse `device_id`, `driver_status`, `latitude`, `longitude`, `timestamp`.
   - Validate the device exists (`device_uid` lookup) — silently log and discard unknown device IDs (do not crash the listener).
   - Dispatch a queued job `ProcessDeviceTelemetry` (do not process synchronously inside the MQTT loop — keep the listener light).
3. `ProcessDeviceTelemetry` job:
   - Inserts a `device_telemetry` row.
   - Updates `devices.last_seen_at` and `devices.status`.
   - If `driver_status` is `drowsy` or `microsleep`, creates a `DeviceAlert` and fires a `DriverAlertTriggered` event.
   - Broadcasts an event (`DeviceTelemetryUpdated`) on a private channel scoped per company (e.g. `company.{company_id}.devices`) so Filament dashboards update live via Echo.
4. Run the `mqtt:listen` command as a **supervisor-managed long-running process** (document a sample `supervisord` config), separate from `queue:work`.
5. Add a scheduled command `devices:mark-offline` that flags devices as `offline` if `last_seen_at` is older than N minutes (configurable), and broadcasts that status change too.

---

## 4. Filament Panel Structure

Use **Filament's multi-tenancy** feature, tenant model = `Company`.

- **Super admin panel** (`/admin`): no tenancy scoping, sees all companies, devices, users. Full CRUD on `CompanyResource`, `UserResource`, `DeviceResource`, `DeviceAlertResource`, plus a global settings page (MQTT broker config, alert thresholds, offline timeout).
- **Company panel** (`/app`, tenant-scoped): `company_admin` and `company_operator` roles only see their own company's data (Filament tenancy auto-scopes queries).

### Resources

- **CompanyResource** (super admin only): name, contact info, active toggle, related devices/users tables as relation managers.
- **DeviceResource**: create/edit device, assign to company (super admin can reassign; company admin cannot), driver name, vehicle plate, status badge, `last_seen_at` column, relation manager showing recent telemetry/alerts.
- **UserResource**: manage users + role assignment (guard against company users elevating their own role — enforce via Filament policies).
- **DeviceAlertResource**: list of alerts with acknowledge action, filters by status/date/company.

### Dashboard (main feature)

Build a custom Filament page (`app/Filament/Pages/Dashboard.php` or a dedicated `FleetMonitor` page) with:

- A **live map widget** (Leaflet inside a Blade/Livewire component) showing a marker per active device, colored by current status (green = normal, yellow = drowsy, red = microsleep, gray = offline). Markers update in real time via Laravel Echo listening to the company's broadcast channel — no full page reload.
- A **stats widget row**: total devices, active now, drowsy/microsleep alerts today, offline devices.
- A **live alerts table widget**: most recent `DeviceAlert`s, auto-refreshing, with an "Acknowledge" action.
- Clicking a marker or table row opens a slide-over with that device's recent telemetry history (small sparkline/chart of status over the last hour, using a Filament chart widget).

---

## 5. Authorization & Multi-Tenancy Rules

- Company users must never query or see another company's devices/telemetry/alerts — enforce with global Eloquent scopes tied to Filament tenancy, **not** just hidden UI (defense in depth: add policy checks on every Resource).
- `company_operator` can view the dashboard and acknowledge alerts but cannot manage devices/users.
- `company_admin` can manage devices/users within their own company only.
- `super_admin` bypasses all tenant scoping.
- Write Filament **Policies** for every model (`CompanyPolicy`, `DevicePolicy`, `UserPolicy`, `DeviceAlertPolicy`) rather than relying on implicit visibility.

---

## 6. Code Quality Requirements

- Strict typing (`declare(strict_types=1)`) in all new PHP files.
- Use **Form Requests** or Filament's built-in validation for all inputs; validate MQTT payload structure defensively before DB writes (reject malformed payloads, log them).
- Extract MQTT parsing/business logic into dedicated service classes (e.g. `App\Services\TelemetryIngestionService`) — keep the Artisan command and Job thin.
- Use **Enums** (PHP 8.1 backed enums) for `driver_status` and `device status`, not raw strings, with a Filament-friendly `label()`/`color()` method for badges.
- Use database transactions where multiple related writes happen (telemetry insert + alert creation).
- Write **Pest tests** covering: MQTT payload processing job, alert creation logic, tenancy scoping (company A cannot see company B's devices), and policy authorization.
- Add API-level rate/error handling: if MQTT payload is malformed or device unknown, log via a dedicated `mqtt` log channel instead of throwing unhandled exceptions that could kill the listener.
- Document environment variables needed (`MQTT_HOST`, `MQTT_PORT`, `MQTT_USERNAME`, `MQTT_PASSWORD`, `MQTT_CLIENT_ID`, broadcast driver config) in `.env.example`.
- Provide a seeder with demo data: 2–3 companies, several devices each, and randomized telemetry history so the dashboard is demo-ready out of the box.

---

## 7. Deliverables Checklist

Ask the coding tool to produce, in this order:

1. Laravel project scaffold + package installation list (composer commands).
2. Migrations + models + enums + factories/seeders.
3. `php-mqtt` config, the `mqtt:listen` command, `ProcessDeviceTelemetry` job, broadcasting event.
4. Roles/permissions setup (Spatie) + Filament Shield config.
5. Filament panels (admin + tenant-scoped company panel), resources, policies.
6. Dashboard page with map widget, stats widget, live alerts widget.
7. Scheduled `devices:mark-offline` command + `supervisord` sample configs for `mqtt:listen` and `queue:work`.
8. Pest test suite.
9. README with setup steps, including how to simulate an edge device publishing test MQTT messages (e.g. a `mosquitto_pub` example command).

---

## 8. Example MQTT Payload (for reference/testing)

```json
{
  "device_id": "DMS-0042",
  "driver_status": "microsleep",
  "latitude": -6.914744,
  "longitude": 107.609810,
  "timestamp": "2026-08-29T14:32:05Z"
}
```
