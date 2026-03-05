<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment('Inspiring quote...');
});

/*
|--------------------------------------------------------------------------
| MPESA CLEANUP JOB
|--------------------------------------------------------------------------
*/

Schedule::command('mpesa:cleanup')->everyFiveMinutes();