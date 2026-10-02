<?php
return [
    'symbol' => 'BTCUSDT',
    'interval' => '1h',
    'market_data_url' => env('ASTRA_MARKET_DATA_URL', 'https://api.binance.com'),
    'market_data_delay_seconds' => (int) env('ASTRA_MARKET_DATA_DELAY_SECONDS', 5),
    'max_candle_age_seconds' => (int) env('ASTRA_MAX_CANDLE_AGE_SECONDS', 3900),
    'execution_enabled' => false, // Intentionally hard-disabled through Milestone 2.
];
