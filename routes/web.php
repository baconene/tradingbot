<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketDataController;
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/api/status', fn () => response()->json([
    'project'=>'GPT Astra','mode'=>'paper','execution_enabled'=>false,
    'exchange_connected'=>false,'kill_switch'=>'locked',
]));
Route::get('/api/market-data', MarketDataController::class)->name('market-data');

// Read-only research endpoints. Never expose order mutation routes before authentication and certification.
Route::get('/api/research/backtests', \App\Http\Controllers\ResearchController::class);
Route::get('/api/risk/status', \App\Http\Controllers\RiskStatusController::class);
Route::get('/api/portfolio', \App\Http\Controllers\PortfolioController::class);
