# Read-only research export and bounded optimizer

Set a long random `ASTRA_RESEARCH_EXPORT_TOKEN` in Forge environment settings (never commit it). Run `php artisan optimize:clear` after changing the environment. The endpoint remains unavailable (401) when the token is unset.

```sh
curl -H "Authorization: Bearer $ASTRA_RESEARCH_EXPORT_TOKEN" \
  'https://tradingbot-d4tturik.on-forge.com/api/research/backtests/export?limit=5'
```

This endpoint exports saved backtest reports including trade-by-trade results, assumptions, data hashes and dates. It cannot run optimization, mutate settings or submit orders. Do not paste the token into a public chat or expose it in browser-side JavaScript.

After a gap-free backfill of at least 1,500 completed hourly candles:

```sh
php artisan astra:optimize
```

The optimizer tests 18 predeclared candidate configurations on the earliest 75% of data and evaluates **only the training-selected configuration** on the final 25%, with 200 warmup candles. It persists all candidate training metrics and untouched holdout results as a research-only backtest record. It does **not** automatically adopt a candidate, recurse against the holdout, schedule itself or enable trading. Review net returns, sample sizes, drawdown and stability, not just win rate. The separate dashboard backtest ledger and chart continue to show the latest standard trade-level run.

Future improvement: rolling walk-forward windows, independent venue validation, robust slippage and spread estimates, and confidence intervals. Repeatedly selecting settings after seeing holdout performance invalidates that holdout; reserve a new unseen period for subsequent evaluations.
