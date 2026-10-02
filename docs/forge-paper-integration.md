# GPT Astra: Forge paper-trading integration checklist

The application is still **research-only**. It does not submit orders. The Binance Spot Testnet account diagnostic verifies connectivity only; it is not a certification of order execution.

## 1. Deploy and verify the database
From Forge > Sites > tradingbot > Commands (or SSH into the site directory):

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan astra:sync-candles --max-pages=2
php artisan astra:backtest
php artisan test
```

Initial backfill (after the short sync succeeds):

```bash
php artisan astra:sync-candles --start=2024-10-01
```

If public Binance market-data access is blocked by the VPS region, the sync command fails and the dashboard remains stale. Do not fabricate candles or use Testnet prices as production historical data. Use an approved accessible public-market-data endpoint if necessary.

## 2. Configure Forge Scheduler
Create one cron entry to run every minute:

```bash
php /home/forge/YOUR_SITE/artisan schedule:run >> /dev/null 2>&1
```

The application schedules the read-only hourly candle sync at minute 1. Replace YOUR_SITE with the actual Forge site directory. Do not add a trading worker yet.

## 3. Optional Binance Spot Testnet read-only check
Generate **Spot Testnet** API credentials from the official Spot Testnet portal, not your production Binance account. In Forge's Environment editor, set:

```dotenv
BINANCE_TESTNET_API_KEY=your_testnet_key
BINANCE_TESTNET_API_SECRET=your_testnet_secret
ASTRA_EXECUTION_ENABLED=false
ASTRA_EXCHANGE_MODE=disabled
```

Never commit keys or paste them into chat. Keep production exchange keys off this VPS. After saving the environment:

```bash
php artisan optimize:clear
php artisan astra:check-testnet
```

The command queries Testnet server time and a signed **GET /api/v3/account**. It does not submit orders. If the result is `credentials_missing`, the Forge environment variables were not loaded. If it is `account_request_rejected`, verify the testnet credentials, testnet availability and outbound HTTPS connectivity. Check the VPS clock if timestamp errors occur.

## 4. Public read-only diagnostics
Visit `/up`, `/api/status`, and `/api/market-data`. The status endpoint reports the candle count and latest candle, but never credentials. The dashboard is only meaningful once migrations and the candle importer have succeeded.

## 5. Before simulated order execution
Implement and verify durable risk state, manual approval, exchange filters, partial fills, order reconciliation, protective exits, testnet resets, independent shadow portfolio and failure drills. Trading must remain disabled until these gates are complete. Never interpret successful deployment or account connectivity as approval to place orders.
