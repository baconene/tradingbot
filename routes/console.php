<?php
use Illuminate\Support\Facades\Schedule;
// Hourly ingestion only. No trading, order jobs, or exchange credentials.
Schedule::command('astra:sync-candles --max-pages=2')->hourlyAt(1)->withoutOverlapping(20);
