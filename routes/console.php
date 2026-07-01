<?php

use Illuminate\Support\Facades\Schedule;

// Evaluate schedule-triggered flows every minute. Requires the OS cron to call
// `php artisan schedule:run` once a minute in production.
Schedule::command('flows:run-scheduled')->everyMinute()->withoutOverlapping();
