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
 public function liveBacktest(Request $request,FuturesImporter $importer): JsonResponse {
  $data=$request->validate(['pages'=>'required|integer|min:1|max:2',
   'bars'=>'required|integer|min:900|max:15000','rr'=>'required|numeric|min:1|max:5',
   'risk'=>'required|numeric|min:0.0001|max:0.01']);
  $lock=Cache::store('file')->lock('astra:research:live',110);
  if(!$lock->get())return response()->json(['message'=>'A live research cycle is already running.'],409);
  try {
   $symbol=config('astra.symbol');
   $import=$importer->sync($symbol,(int)$data['pages']);
   $available=DB::table('futures_candles')->where('symbol',$symbol)->where('interval','1m')->count();
   if($available<900)return response()->json(['message'=>'Import succeeded but at least 900 completed 1m candles are needed. Import more history using the dashboard.',
    'import'=>$import,'available_candles'=>$available],422);
   $lastRun=DB::table('research_backtests')->where('symbol',$symbol)->orderByDesc('id')->first();
   $lastCandle=DB::table('futures_candles')->where('symbol',$symbol)->where('interval','1m')->max('open_ms');
   $sameParameters=$lastRun&&((float)(json_decode($lastRun->parameters,true)['rr']??0)==(float)$data['rr'])
    &&((float)(json_decode($lastRun->parameters,true)['risk_pct']??0)==(float)$data['risk'])
    &&(int)$lastRun->bars===min((int)$data['bars'],$available);
   if($lastRun&&$sameParameters&&(int)$lastRun->last_open_ms===(int)$lastCandle){
    return response()->json(['status'=>'unchanged','message'=>'No new completed candles; existing backtest remains current.',
     'import'=>$import,'run_id'=>$lastRun->id,'metrics'=>json_decode($lastRun->metrics,true),
     'last_candle_ms'=>(int)$lastCandle])->header('Cache-Control','no-store');
   }
   $code=Artisan::call('astra:backtest-scalping',['--bars'=>min((int)$data['bars'],$available),
    '--rr'=>(float)$data['rr'],'--risk'=>(float)$data['risk']]);
   if($code!==0)return response()->json(['message'=>'Backtest refused the imported data. Check for missing 1m candles; no results were fabricated.',
    'import'=>$import,'available_candles'=>$available],422);
   $run=DB::table('research_backtests')->where('symbol',$symbol)->orderByDesc('id')->first();
   return response()->json(['status'=>'completed','import'=>$import,'run_id'=>$run->id,
    'metrics'=>json_decode($run->metrics,true),'last_candle_ms'=>(int)$lastCandle])
    ->header('Cache-Control','no-store');
  }catch(Throwable $e){
   report($e);
   return response()->json(['message'=>'Live research cycle failed. Verify Binance Futures API access and application logs.'],503);
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