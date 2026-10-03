# Astra Futures V2 — implementation status and rollout

## Implemented in foundation release
- Separate futures observation, execution-event, risk-snapshot and immutable strategy-configuration database tables. Existing spot backtests and models remain intact.
- Mobile Futures V2 workspace with 1–100x **research-only** leverage, RR, structure lookback, short research toggle and risk controls. Saving creates a new draft; existing versions are never modified.
- Standalone futures risk preflight for *hypothetical* long and short orders, including fees, estimated funding, exchange lot/minimum inputs, notional and margin buffer. It does not calculate liquidation price and cannot authorize orders.
- Offline Python modeling scaffold in `services/model-research`. It consumes explicitly labeled, point-in-time features and rejects overlapping train/holdout trade labels. No model is approved by its output.
- Read-only `GET /api/futures/terminal`; no futures order routes. `php artisan astra:futures-config "Experiment name" --leverage=2 --rr=2` creates a draft on the trusted server.

## Security requirement before exposing configuration mutations
`POST /api/futures/configs` MUST be protected by authenticated operator middleware. Do not publicly expose this route on Forge. The initial foundation includes an environment-backed operator token guard. Set `ASTRA_FUTURES_CONFIG_TOKEN` to a long random secret in Forge and supply `Authorization: Bearer ...` only from a trusted operator client. The public browser terminal is intentionally read-only unless an authenticated operator flow is added. Never place the token in frontend JavaScript, HTML or browser storage.

## Not yet implemented
Verified futures market ingestion, exchange contract/filter discovery, historical mark/funding/OI completeness, futures-grade backtesting, TradingView Lightweight Charts integration, Laravel AI SDK agent installation, MLflow server deployment, signed testnet REST/WebSocket integration, protective exit management, partial-fill reconciliation, failover drills, live credentials and live trading. Existing chart and prediction lab still use **spot** data; never interpret their signals as futures validated.

## Deployment
Run `php artisan migrate --force` through the existing Forge deploy script, `php artisan test`, and `npm run build`. Create a draft with the CLI or use a trusted operator API client. Keep existing execution kill latch enabled. Do not enable live trading or 100x exchange leverage.
