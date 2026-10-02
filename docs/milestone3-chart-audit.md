# Milestone 3 audit and research chart (2026-10-03)

## Implemented
- Deterministic 1h BTCUSDT momentum/breakout strategy with previous 20 high, EMA20/50, RSI14, relative volume, ATR stop and 2R target.
- Historical next-hour-open entries, stop-first ambiguous-bar handling, fixed fees/slippage assumptions, six-bar exit, 0.5% risk sizing, 25% position cap, stored immutable input hash and backtest results.
- Read-only `/api/chart` with up to 240 completed hourly candles, EMA20, EMA50, previous-20-high, RSI14, ATR14, volume and historical backtest entry/exit markers.
- Dashboard refreshes the chart every 60 seconds. This is **not tick-level streaming** and does not imply current data when historical backfill is incomplete.

## Still pending before Milestone 3 acceptance
- Finish two-year historical backfill and confirm newest candle is fresh; 2,000 candles alone is insufficient.
- Representative 2+ years where available; independently validate data provenance, missing periods and venue consistency.
- Out-of-sample and walk-forward tests, parameter sensitivity, multiple-testing controls and independent review.
- Exchange-filter-aware simulated fills, observed bid/ask spread and slippage, Sharpe and other robust performance statistics.
- Verify backtest execution and browser chart against real Forge database; no live or Testnet execution is enabled.

## Activate hourly data refresh in Forge
In Forge > Sites > tradingbot > Scheduler, create a task running `php artisan schedule:run` every minute from the site's current release directory. Laravel's `routes/console.php` already schedules `astra:sync-candles --max-pages=2` at minute 1 every hour. This is **market-data ingestion only**, not a trading scheduler. Run `php artisan astra:sync-candles --max-pages=0` manually first to complete historical backfill. Verify `/api/market-data` returns `fresh: true`. The app cannot create the Forge scheduler itself without access to your Forge account.

To display backtest markers, run `php artisan astra:backtest` after a complete, gap-free backfill. Markers represent simulated historical trades only. If a backtest predates a different data source or changed historical data, rerun it before interpreting overlays.

## Diagnostics and validation upgrade
- Each closed trade includes point-in-time signal context: RSI, relative volume, ATR, breakout size in ATR, signal-bar range in ATR and EMA spread in ATR; no future candles are used in the signal.
- Reports include exit-reason counts, win counts and net P&L, profit factor, and annualized Sharpe computed from hourly realized-equity changes (zero-return hours included; not mark-to-market and not comparable to full portfolio Sharpe).
- Optional research-only `min_breakout_atr`, `max_breakout_atr`, and `max_signal_range_atr` parameters support controlled false-breakout hypotheses. Defaults preserve the original strategy.
- `php artisan astra:walk-forward` runs four chronological training/test folds with training-only selection and frozen subsequent test evaluation. These folds overlap the previously inspected October 2024–October 2026 data, so they are diagnostic rather than untouched independent validation. No candidate is promoted.
- Still pending: observed spread/slippage, venue exchange filters, independently sourced untouched validation, independent review and live market-data freshness checks.
