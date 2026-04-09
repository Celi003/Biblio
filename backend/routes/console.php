<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Library automation
Schedule::command('biblio:process-overdues')->dailyAt('00:10');
Schedule::command('biblio:send-reminders')->dailyAt('08:00');
