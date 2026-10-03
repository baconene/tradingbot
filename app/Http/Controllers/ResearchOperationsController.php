<?php
namespace App\Http\Controllers;
use App\MarketData\FuturesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;
final class ResearchOperationsController {
 public function import(Request $request,FuturesImporter $importer): JsonResponse {
  $data=$request->validate(['pages'=>'required|integer|min:1|max:2']);
  $lock=Cache::store('file')->lock('astra:research:import',110);
  if(!$lock->get())return response()->json(['message'=>'An import is already running.'],409);
  try{
   $result=$importer->sync(config('astra.symbol'),$data['pages']);
   return response()->json($result)->header('Cache-Control','no-store');
  }catch(Throwable $e){
   report($e);
   return response()->json(['message'=>'Import failed. Check Binance Futures access and application logs.'],503);
  }finally{$lock->release();}
 }
 public function backtest(Request $request): JsonResponse {
  $data=$request->validate(['bars'=>'required|integer|min:900|max:15000','rr'=>'required|numeric|min:1|max:5','risk'=>'required|numeric|min:0.0001|max:0.01']);
  $lock=Cache::store('file')->lock('astra:research:backtest',110);
  if(!$lock->get())return response()->json(['message'=>'A backtest is already running.'],409);
  try{
   $code=Artisan::call('astra:backtest-scalping',['--bars'=>$data['bars'],'--rr'=>$data['rr'],'--risk'=>$data['risk']]);
   if($code!==0)return response()->json(['message'=>'Backtest could not run. Import at least 900 contiguous completed 1m candles first.'],422);
   $run=DB::table('research_backtests')->orderByDesc('id')->first();
   return response()->json(['run_id'=>$run->id,'trades'=>json_decode($run->metrics,true)['trades'],'metrics'=>json_decode($run->metrics,true)])
    ->header('Cache-Control','no-store');
  }catch(Throwable $e){
   report($e);
   return response()->json(['message'=>'Backtest failed. Check application logs.'],503);
  }finally{$lock->release();}
 }
}