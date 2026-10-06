# Appointment Email Integration

This app polls the live appointment project every 15 minutes, queues accepted appointments scheduled for tomorrow in the configured reminder timezone, and sends the accepted-status email. Configure this app's default database and database queue connection to MariaDB before deployment; appointment records remain in the live project.

## Live API Contract

Configure `APPOINTMENT_API_URL`, `APPOINTMENT_API_TOKEN`, and optional endpoint paths in the worker app's `.env`. Requests use a Bearer token and JSON. The worker uses these defaults:

- `GET /api/internal/appointment-email-candidates?due_before={RFC3339}&limit=100&cursor={cursor}`
- `PATCH /api/internal/appointments/{activity_id}/email-status`

Candidate response:

```json
{
  "data": [
    {
      "appointment_id": "appt-8391",
      "activity_id": "activity-8391",
      "patient_name": "Taylor Morgan",
      "patient_email": "taylor@example.com",
      "requested_at": "2026-10-03T10:00:00-04:00",
      "preferred_doctor": "Dr. Kari Michael Manayan Lopez",
      "facility": "Primary Health Care Facility",
      "facility_address": "123 Main Street",
      "video_url": "https://example.test/video/appt-8391",
      "appointment_status": "accepted",
      "email_sent_status": "pending"
    }
  ],
  "next_cursor": null
}
```

`appointment_id` may identify a shared schedule or slot and is not used as the email delivery key. `activity_id` must be the unique primary key of one individual booking row and must be present in every candidate. The Live status route must query `activity.id = activity_id`, never select the first row matching the shared `appointment_id`, and return 404 when that activity does not exist.

The live endpoint should return accepted appointments with `requested_at <= due_before` where `email_sent_status` is `pending`, or `processing` with an expired `claimed_until`. Include past-due records so the worker can mark them `expired`. Times must include a timezone. The worker uses `APPOINTMENT_REMINDER_TIMEZONE` (default `Asia/Manila`) to select appointments whose local calendar date is tomorrow; other future dates are skipped. The worker claims each eligible candidate by PATCHing the unique activity ID:

```json
{
  "email_sent_status": "processing",
  "claim_token": "unique-token",
  "claimed_until": "2026-10-02T16:00:00Z"
}
```

Return `{"claimed": true}` only when the API atomically changed that activity row from pending, or reclaimed an expired processing claim, to processing; otherwise return `{"claimed": false}`. After successful delivery, the worker PATCHes `email_sent_status: sent` and the same claim token. The Live API must verify that token matches the activity's active claim before marking it sent. Past appointments are PATCHed to `expired`. Do not add a uniqueness constraint on schedule, slot, or appointment time; only the per-booking activity ID is unique.

The worker only dispatches after a successful claim. It stores the payload/status locally so queue retries after a successful send synchronize the live status without resending the email. As with any SMTP delivery, a process crash between provider acceptance and local persistence cannot be made exactly-once without provider-side idempotency.

## Queue and Scheduler

MariaDB is active on the host. Preserve the current database configuration; `DB_CONNECTION=mysql` is Laravel's PDO MySQL driver and can connect to MariaDB. The email job explicitly uses Laravel's `database` queue connection, so the global queue driver can remain unchanged. With `DB_QUEUE_CONNECTION` unset, Laravel uses the default database connection. Back up the database and run the new `2026_10_06_000000_rename_appointment_email_delivery_key_to_activity_id` migration before deployment. Review existing delivery rows first; their old keys must already identify unique booking rows to remain valid as `activity_id`. Configure the host scheduler to invoke Laravel's scheduler each minute:

```cron
* * * * * cd /var/www/telemedUploadAPI && php artisan schedule:run >> /dev/null 2>&1
```

The cron entry is installed at `/etc/cron.d/telemed-email-scheduler`. Laravel runs `appointments:poll-email-reminders` every 15 minutes with overlap prevention, and skips the poll until both live API settings are present and a real mailer is configured. The `telemed-email-worker` Supervisor program is installed and running; its config is based on `deploy/supervisor/telemed-email-worker.conf.example` and consumes only:

Run the scheduler and worker as a consistent deployment user, and ensure that user can write to `storage/framework/cache`, `storage/logs`, and `bootstrap/cache`. A scheduler cache-lock permission error prevents the scheduled poll from running.

```sh
php artisan queue:work database --queue=appointment-emails --tries=5 --timeout=60
```

The legacy `laravel-worker` is stopped with `autostart=false` and `autorestart=false`. To reproduce the worker installation on another host, install the example and reload Supervisor:

```sh
sudo install -o root -g root -m 0644 /var/www/telemedUploadAPI/deploy/supervisor/telemed-email-worker.conf.example /etc/supervisor/conf.d/telemed-email-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start 'telemed-email-worker:*'
sudo supervisorctl status
```

Install the cron template on another host with `sudo install -o root -g root -m 0644 /var/www/telemedUploadAPI/deploy/cron/telemed-email-scheduler.example /etc/cron.d/telemed-email-scheduler`.

Do not include the `default` queue. This worker must not process the existing video-conversion job. Configure production mail transport separately; the sample `MAIL_MAILER=log` does not deliver email.

Set `APPOINTMENT_API_URL` and `APPOINTMENT_API_TOKEN` before running the poll command. Configure a real production mail transport as well; if `MAIL_MAILER` is unset, Laravel defaults to the `log` mailer and will not deliver email.

After setting the API and mail values in `.env`, set `APPOINTMENT_API_STATUS_PATH=/api/internal/appointments/{activity_id}/email-status`, refresh cached config, and restart the long-running worker:

```sh
sudo -u www-data /usr/bin/php /var/www/telemedUploadAPI/artisan config:cache
sudo supervisorctl restart 'telemed-email-worker:*'
```

The scheduled poll remains disabled until the API URL/token exist and the default mailer is not `log`, `array`, or `failover`.