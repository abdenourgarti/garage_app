<?php

use Illuminate\Support\Facades\Schedule;

// Planificateur de tâches Windows → `php artisan schedule:run` chaque minute (installé en phase 9).
Schedule::command('backup:run --if-missing')->hourly()->withoutOverlapping();
