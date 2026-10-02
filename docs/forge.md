# Deploying GPT Astra Milestone 1 to Laravel Forge

1. Provision a Forge VPS with **PHP 8.3 or 8.4**, Nginx, Node.js 22+, PostgreSQL and Composer 2. The application uses Laravel 13.
2. Create a private Git repository for this folder, then create a Forge site linked to that repository. Set web directory to `public`.
3. In Forge's environment editor, copy `.env.example`, set `APP_ENV=production`, `APP_DEBUG=false`, the actual `APP_URL`, database connection, and generate a unique APP_KEY with `php artisan key:generate` after the first deployment. Never commit production `.env`.
4. Provision PostgreSQL database `gpt_astra`, create a least-privilege DB user, and ensure required PHP extensions are installed.
5. Use `deploy/forge-deploy.sh` as a starting deployment script. Set `FORGE_SITE_PATH` to the actual site directory. `npm ci` requires a generated and committed `package-lock.json`; run `npm install` once locally and commit it before using the script.
6. Configure SSL in Forge. Keep this Milestone 1 dashboard behind Forge site-level HTTP authentication or a private network until application authentication is implemented. The `/api/status` endpoint reveals only non-sensitive status.
7. Verify `GET /up`, `GET /api/status`, the dashboard, database migrations, and `php artisan test`.
8. **Do not** configure Binance API credentials, scheduler or trading workers in Milestone 1. Execution is hard-disabled in source.

## Rollback
Use Forge's previous deployment / prior Git commit and redeploy. Back up the database before schema migrations. The initial foundation migration only creates strategy, risk-event and audit tables plus Laravel infrastructure. Do not roll back database migrations that would delete audit records after real trading is implemented.

## Known installation limitation
The package was assembled in an offline environment. Composer and NPM dependencies and lockfiles are not included; run `composer install` and `npm install` in a connected environment, commit `composer.lock` and `package-lock.json`, then run tests and build before first Forge deployment.

## Milestone 2 market-data setup
After deploying and running migrations, execute `php artisan astra:sync-candles --start=2024-10-01` once via Forge SSH (initial history can take multiple pages). Configure Forge Scheduler to run `php artisan schedule:run` every minute; Laravel invokes read-only sync at minute 1. Keep `ASTRA_EXECUTION_ENABLED=false` and never configure exchange trading keys. Review `docs/milestone2-market-data.md` before scheduling.
