<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands as well as scheduled maintenance commands for Laravel 13.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('p4i:reconcile-exports --stale-minutes=15')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
