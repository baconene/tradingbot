# Milestone 2 — Market data and deterministic features

## Boundaries
- Public **production** Binance Spot `/api/v3/klines` for BTCUSDT 1h only. This service never uses API keys, submits orders, or connects to Testnet.
- Binance Testnet execution remains disabled. Historical spread, order-book imbalance, and order flow are explicitly `null` until real point-in-time observations are available.
- Only closed hourly candles whose close timestamp plus configured publication delay is at or before ingestion time are stored.
- Existing candles are immutable (`insertOrIgnore`); a correction requires an explicit future versioned reconciliation process.
- Snapshot creation requires >=200 **consecutive** hourly candles. The previous 20-candle breakout level and relative-volume denominator exclude the current candle. EMA uses a 200-bar warmup; RSI and ATR use Wilder smoothing.
- Historical `available_at` is the earliest *assumed* availability (close + configured delay), not a verified exchange publication timestamp. Live ingest must use observed arrival time when research requires exact availability.

## Commands
```
php artisan migrate
php artisan astra:sync-candles --start=2024-10-01 --max-pages=3
php artisan astra:sync-candles
php artisan schedule:run
php artisan test
```

Run the initial historical backfill **manually** on Forge. The scheduled hourly sync runs at minute 1 UTC; set Forge's scheduler to execute `php artisan schedule:run` every minute. No trading scheduler exists.

## API and dashboard
- `GET /api/market-data` shows the latest stored completed candle, feature snapshot, data quality, and execution-disabled status.
- Dashboard displays live database observations on page load, not streamed real-time prices.
- If a sync fails, the scheduler logs an error and the dashboard eventually marks the feed stale. No trading can occur.

## Known limitations and Milestone 3 dependencies
- Production Binance may be region-restricted. Do not silently substitute Testnet historical data. Configure a licensed production-market data provider and validate compatibility if required.
- The backfill reports gaps within each fetched page. The snapshot builder rejects any gap in the latest 200–250 bars. A separate historical coverage report and durable gap table are future improvements.
- HTTP retry is bounded, but request-weight accounting and `Retry-After` handling need validation under prolonged backfills.
- No historical bid/ask or order-book archive is provided by the kline endpoint. Backtesting must use explicit cost assumptions, not fabricated historical spread observations.
- The endpoint is read-only and unauthenticated in this milestone; deploy behind site-level authentication until application auth is implemented.

## Rollback
Disable Forge's scheduler, redeploy the prior commit, and preserve `market_candles`/`market_snapshots` for audit. Do **not** run `migrate:rollback` against production data without a backup. Trading remains hard-disabled regardless of rollback.
