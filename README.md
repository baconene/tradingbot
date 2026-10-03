# Astra Futures research — phases 1–3
Laravel 13 + Inertia/Vue 3. **Research only: no authenticated trading endpoints, exchange credentials, or order submission.**

## Implemented
- Public Binance USDⓈ-M 1m klines importer (completed candles only), paginated with gaps reported
- 1m/5m/15m charts, EMA20/50, RSI14, ATR14, previous 20 highs/lows
- Offline 5m breakout / 15m EMA50 confirmation backtest. Next 1m open entries; same-bar stop before target; fees and slippage; capped risk and notional
- Stored immutable run metrics, simulated trades and realized equity curve. Actual win rate is blank until a run exists
- Responsive Vue dashboard with synchronized chart timestamps and backtest ledger

## Local
```bash
cp .env.example .env
composer install && npm ci
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan astra:sync-futures --pages=20
php artisan astra:backtest-scalping --bars=15000 --rr=2
npm run build
php artisan serve
```
If using PostgreSQL on Forge, configure DB_CONNECTION/host/user/password in Forge. Install Node 22 and configure Forge's deployment script to match `deploy/forge-deploy.sh`. Configure Forge scheduler to run `php artisan schedule:run` every minute. Do not expose the backtest CLI through an unauthenticated public POST endpoint.

## Data limitations
Binance may reject access from a hosting region; do not circumvent restrictions. Import at least 900 **contiguous** completed 1m candles. For useful backtests import many days and validate missing periods. Missing 1m candles prevent backtesting rather than being filled. No futures funding, mark-price liquidation, spread order book, out-of-sample validation, ML predictions or live execution in this release. Current 2R breakout is an experimental baseline, not a proven strategy. Imported historical candles are fetched from the public Futures API; the previous Spot API keys are not required.

## Deployment warning
Previous Forge release may still have obsolete deployment scripts. Replace the Forge UI script with the contents of `deploy/forge-deploy.sh`. Back up the old production database and disable old queue workers/schedules before enabling this release. The migration creates new tables; it does not delete old tables or historical data.

## UI-controlled research operations
Set a random 32+ character `ASTRA_RESEARCH_OPERATOR_TOKEN` once in Forge's Environment editor. The deployment script migrates the database automatically. After deployment, open the dashboard, enter the token into the Research Controls panel, and click **Import futures candles** or **Run backtest**. The token is held only in the current browser component's memory (never in localStorage or the URL). Do not share the token or expose it in screenshots. Import is limited to two API pages per click to keep HTTP requests bounded; repeat to backfill more history. No Forge terminal commands or scheduled jobs are required for manual testing. For continuous ingestion, Forge scheduler configuration is optional and separate. The operator token is not a trading API key; it only authorizes offline research operations.

## Rolling live-data backtest from the UI
Use **Sync & backtest now** to import the newest *closed* Binance USD-M 1m candles and recompute the offline backtest. **Start auto-cycle** repeats every 90 seconds only while the browser tab remains open and the operator token is entered. The cycle skips duplicate runs when no new closed candle has arrived and parameters are unchanged. Import history from the UI until at least 900 contiguous candles are present; a gap fails closed. This is a periodically refreshed **historical backtest**, not an out-of-sample forward paper-trading record, exchange websocket stream, ML forecast, or live order execution. No performance results are claimed until the server completes a real run.
