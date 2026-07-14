<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('memoryvault:cleanup')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('memoryvault:process-cleanup-queue')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('memoryvault:health')->everyFiveMinutes()->withoutOverlapping();
