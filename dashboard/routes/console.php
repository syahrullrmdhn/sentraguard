<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Mark agents offline when heartbeats stop arriving (runs every minute).
Schedule::command('agents:mark-offline')->everyMinute();

// Time out commands that exceeded their timeout window (runs every minute).
Schedule::command('commands:timeout')->everyMinute();

// Prune old metrics and audit logs daily at 02:00.
Schedule::command('metrics:prune')->dailyAt('02:00');
