<?php
return ['default' => env('CACHE_STORE', 'database'), 'stores' => [
    'array' => ['driver' => 'array'], 'database' => ['driver' => 'database', 'connection' => null, 'table' => 'cache', 'lock_connection' => null, 'lock_table' => 'cache_locks'],
    'redis' => ['driver' => 'redis', 'connection' => 'cache'], 'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
], 'prefix' => env('CACHE_PREFIX', 'astra_cache')];
