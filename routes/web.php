<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FuturesResearchController;
Route::get('/',fn()=>\Inertia\Inertia::render('Dashboard'))->name('home');
Route::get('/api/futures/market',[FuturesResearchController::class,'market'])->middleware('throttle:30,1');
Route::get('/api/research/backtest',[FuturesResearchController::class,'results'])->middleware('throttle:30,1');
