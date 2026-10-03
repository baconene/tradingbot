<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuturesResearchController;
Route::get('/',fn()=>\Inertia\Inertia::render('Dashboard'))->name('home');
Route::get('/api/futures/market',[FuturesResearchController::class,'market'])->middleware('throttle:30,1');
Route::get('/api/research/backtest',[FuturesResearchController::class,'results'])->middleware('throttle:30,1');

Route::post('/api/research/import',[\App\Http\Controllers\ResearchOperationsController::class,'import'])->middleware(['research.operator','throttle:4,1']);
Route::post('/api/research/run',[\App\Http\Controllers\ResearchOperationsController::class,'backtest'])->middleware(['research.operator','throttle:2,1']);
