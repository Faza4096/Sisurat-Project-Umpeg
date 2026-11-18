<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat disposisi setiap pagi pada hari kerja.
Schedule::command('disposisi:ingatkan')->dailyAt('07:30')->weekdays();
