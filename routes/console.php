<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('memoryvault:cleanup')->everyFiveMinutes();
Schedule::command('memoryvault:process-cleanup-queue')->everyFiveMinutes();
Schedule::command('memoryvault:health')->everyFiveMinutes();
