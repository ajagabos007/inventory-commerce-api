<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->daily();

Schedule::command('guest:clear-sessions')->daily()
    ->runInBackground()->withoutOverlapping();

Schedule::command('analytics:aggregate-model-views')->daily()
    ->runInBackground()->withoutOverlapping();


