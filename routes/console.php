<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:poll-email-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => filled(config('services.appointment_api.url'))
        && filled(config('services.appointment_api.token'))
        && !in_array(config('mail.default'), ['log', 'array', 'failover'], true));
