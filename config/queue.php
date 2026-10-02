<?php
return ['default' => env('QUEUE_CONNECTION', 'database'), 'connections' => [
    'sync' => ['driver' => 'sync'], 'database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => (int) env('ASTRA_TRAIN_RETRY_AFTER', 2100), 'after_commit' => true],
    'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => env('REDIS_QUEUE', 'default'), 'retry_after' => 120, 'block_for' => 5, 'after_commit' => true],
], 'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'pgsql'), 'table' => 'failed_jobs']];
