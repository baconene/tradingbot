<?php
return [
    'symbol' => 'BTCUSDT',
    'interval' => '1h',
    'market_data_url' => env('ASTRA_MARKET_DATA_URL', 'https://api.binance.com'),
    'market_data_delay_seconds' => (int) env('ASTRA_MARKET_DATA_DELAY_SECONDS', 5),
    'max_candle_age_seconds' => (int) env('ASTRA_MAX_CANDLE_AGE_SECONDS', 3900),
    'testnet_url' => 'https://testnet.binance.vision',
    'testnet_api_key' => env('BINANCE_TESTNET_API_KEY', ''),
    'testnet_api_secret' => env('BINANCE_TESTNET_API_SECRET', ''),
    'research_export_token' => env('ASTRA_RESEARCH_EXPORT_TOKEN', ''),
    'research_training_token' => env('ASTRA_RESEARCH_TRAIN_TOKEN', ''),
    'execution_enabled' => false, // Read-only testnet diagnostics; order submission is locked.
];
