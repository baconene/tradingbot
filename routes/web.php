<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketDataController;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/api/status', function () {
    $market = ['connected' => false, 'candles' => 0, 'latest_candle' => null, 'reason' => 'not_initialized'];
    try {
        $latest = DB::table('market_candles')->where('symbol', 'BTCUSDT')
            ->where('interval', '1h')->orderByDesc('open_time')->first();
        $market = [
            'connected' => $latest !== null,
            'candles' => DB::table('market_candles')->where('symbol', 'BTCUSDT')->where('interval', '1h')->count(),
            'latest_candle' => $latest?->close_time,
            'reason' => $latest ? 'data_imported' : 'no_candles_imported',
        ];
    } catch (\Throwable $e) {
        $market['reason'] = 'database_unavailable_or_migrations_pending';
    }
    return response()->json([
        'project' => 'GPT Astra',
        'mode' => 'paper_research',
        'execution_enabled' => false,
        'exchange_connected' => false,
        'kill_switch' => 'locked',
        'market' => $market,
        'next_steps' => $market['connected'] ? [] : ['php artisan migrate --force', 'php artisan astra:sync-candles --max-pages=2'],
    ]);
});
Route::get('/api/market-data', MarketDataController::class)->name('market-data');

// Read-only research endpoints. Never expose order mutation routes before authentication and certification.
Route::get('/api/research/backtests', \App\Http\Controllers\ResearchController::class);
Route::get('/api/risk/status', \App\Http\Controllers\RiskStatusController::class);
Route::get('/api/portfolio', \App\Http\Controllers\PortfolioController::class);

Route::get('/api/chart', \App\Http\Controllers\ChartController::class)->name('chart');

Route::get('/api/research/backtests/export', \App\Http\Controllers\BacktestExportController::class)->middleware('throttle:30,1');

// Explicit operator token required for research mutations; never accept anonymous retraining.
Route::get('/api/research/training', [\App\Http\Controllers\ResearchTrainingController::class, 'index'])->middleware('throttle:30,1');
Route::post('/api/research/training', [\App\Http\Controllers\ResearchTrainingController::class, 'store'])->middleware('throttle:3,1');

// Public read-only-equivalent hypothetical calculation; never persists or submits an order.
Route::post('/api/risk/shadow-evaluate', \App\Http\Controllers\ShadowRiskController::class)->middleware('throttle:20,1');
