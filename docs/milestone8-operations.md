# Forge VPS staging runbook

1. Provision PHP 8.3+ compatible with Laravel 13, PostgreSQL, Redis, Node 22+, HTTPS and a private Forge site. Pin tested dependency lockfiles in CI before production. Configure the app as a staging/research service, not a trading system.
2. Configure `.env` from `.env.example`, with `APP_ENV=production`, `APP_DEBUG=false`, `ASTRA_EXECUTION_ENABLED=false`, `ASTRA_EXCHANGE_MODE=disabled`. Generate APP_KEY securely. Restrict the dashboard with VPN, reverse-proxy authentication or Laravel authentication; read-only API is not an authorization substitute.
3. Forge deploy: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && npm ci && npm run build && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`. Use `npm install` to create and commit a lockfile before `npm ci`; if the current source has no lockfile, deployment is blocked until one is committed.
4. Schedule `php artisan schedule:run` every minute. Queue worker only when queue jobs are introduced; use Forge daemon with supervised restart. Protect DB and Redis from public exposure. Configure automated encrypted DB backups and verify restore on separate staging.
5. Backfill candles, verify gap report and snapshot hashes, run `php artisan astra:backtest`, review all assumptions and OOS results. Do not mistake a green health endpoint for execution readiness.
6. Gate for testnet activation: independently audited signed API client; minimum filters and partial fills; restart/reconcile; idempotency and uncertain order tests; protected exits; kill-switch drill; shadow price feed and fill comparator; authenticated manual approvals; alerting; complete incident runbook.
7. Only after gates pass: begin 30+ calendar days of observed paper trading. Record missed bars, outages, fees, slippage, daily PnL, realized/unrealized risk, trade sample and deviations. A testnet success never authorizes live trading.

## WHAT COULD BLOW UP THIS ACCOUNT?
- Gapped or delayed candles, look-ahead bias, unstable indicator warmups.
- Spread/slippage/fees overwhelm a small hourly breakout edge; testnet fills misrepresent real markets.
- Stop-loss not guaranteed in a gap; network outages and stale protective orders.
- Duplicated orders after ambiguous HTTP timeouts; partial fills not reconciled.
- Volatility regimes change; fitted strategy fails out of sample.
- Credentials leaked, approval endpoints exposed, database corruption, VPS compromise.
- Incorrect daily reference, drawdown high-water mark, or restart state accidentally resets limits.

**Current safeguard:** all exchange submission is hard-locked in source, risk state starts latched and unreconciled. This does not substitute for completing the missing controls before any trading.
