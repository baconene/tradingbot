# GPT Astra research training UI

The **Retrain research** button runs three historical research commands in a queued worker:
`astra:backtest`, `astra:optimize`, and `astra:walk-forward`. This is bounded parameter research, **not machine learning**, not online learning, and never changes the live strategy or places an order.

## Forge setup

Add a new strong, randomly generated `ASTRA_RESEARCH_TRAIN_TOKEN` to the site's server-only environment. Do not reuse the export token or expose it in JS. Configure `QUEUE_CONNECTION=database` and `ASTRA_TRAIN_RETRY_AFTER=2100`, then deploy and run `php artisan migrate --force && php artisan optimize:clear`.

In Forge > Daemons, create a persistent worker in the site's current release directory:
`php artisan queue:work database --queue=research --sleep=3 --tries=1 --timeout=1800`.
Restart the daemon after deployment. Ensure Forge deploys a current release, that the worker sees the same DB/cache, and that `jobs` and `failed_jobs` infrastructure tables exist. Do not configure `QUEUE_CONNECTION=sync`: the endpoint refuses it.

The button asks for the operator token, holds it only in the current browser component, sends it via HTTPS Authorization header, then clears it after successful dispatch. Protect the entire Forge site with access controls; the read-only research history endpoint is public within the site. A cache lock blocks overlapping requests for one hour; monitor stuck or timed-out jobs in Forge and inspect failed jobs if the status remains queued. Worker process and infrastructure must be configured by the operator.

## Interpretation

The performance curve shows *historical test-period returns across saved experiments*, not a continuously trained AI learning curve. Walk-forward values sum sequential fold returns for visualization and are not compounded. Different datasets and windows make between-run comparison descriptive only. No automatic candidate promotion. Previously examined history is not an independent untouched validation set.

## Live status semantics
The dashboard polls every five seconds while visible and refreshes on window focus. Each job updates a server-side status before and after its three commands; the UI shows completed stages and a last-updated timestamp, not an invented percent-complete estimate. Within each command there is no granular progress event. The latest walk-forward chart plots chronological per-fold test returns and matching baseline rather than connecting unrelated research experiments. If a job stays queued, verify the Forge daemon and the research queue. If a running stage stops updating for over 35 minutes, inspect worker logs and failed jobs.
