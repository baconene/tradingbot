<?php
use Illuminate\Support\Facades\Schedule;
Schedule::command('astra:sync-futures --pages=2')->everyMinute()->withoutOverlapping(5);
