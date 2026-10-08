<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leave:year-end')
    ->yearlyOn(1, 1, '00:01')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/year-end-rollover.log'));
