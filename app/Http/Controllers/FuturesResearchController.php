<?php
namespace App\Http\Controllers;
use App\MarketData\ScalpingFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class FuturesResearchController {
 public function market(Request $request,ScalpingFeatures $features): JsonResponse {
  $symbol=config('astra.symbol');$limit=min(20000,max(900,(int)$request->query('limit',4500)));
  $rows=DB::table('futures_candles')->where('symbol',$symbol)->where('interval','1m')
   ->orderByDesc('open_ms')->limit($limit)->get()->reverse()->values()
   ->map(fn($r)=>['open_ms'=>(int)$r->open_ms,'close_ms'=>(int)$r->close_ms,'open'=>(float)$r->open,
    'high'=>(float)$r->high,'low'=>(float)$r->low,'close'=>(float)$r->close,'volume'=>(float)$r->volume])->all();
  $now=(int)floor(microtime(true)*1000);$last=$rows?end($rows):null;
  $stale=$last===null||$now-$last['close_ms']>config('astra.max_candle_age_seconds')*1000;
  $frames=[];foreach([1,5,15] as $tf)$frames[$tf.'m']=array_slice($features->indicators($features->aggregate($rows,$tf)),-220);
  return response()->json(['symbol'=>$symbol,'market'=>'USD-M futures','source'=>'Binance Futures public klines',
   'stale'=>$stale,'last_candle_ms'=>$last['close_ms']??null,'contiguous'=>$features->contiguous($rows),
   'timeframes'=>$frames,'execution_enabled'=>false])->header('Cache-Control','no-store');
 }
 public function results(Request $request): JsonResponse {
  $run=DB::table('research_backtests')->orderByDesc('id')->first();
  if(!$run)return response()->json(['run'=>null,'trades'=>[],'equity_curve'=>[]])->header('Cache-Control','no-store');
  $trades=DB::table('research_trades')->where('backtest_id',$run->id)->orderByDesc('entry_ms')->limit(500)->get()
   ->map(fn($r)=>['id'=>$r->id,'side'=>$r->side,'signal_ms'=>(int)$r->signal_ms,'entry_ms'=>(int)$r->entry_ms,
    'exit_ms'=>(int)$r->exit_ms,'entry'=>(float)$r->entry,'stop'=>(float)$r->stop,'target'=>(float)$r->target,
    'exit'=>(float)$r->exit,'quantity'=>(float)$r->quantity,'net_pnl'=>(float)$r->net_pnl,
    'fees'=>(float)$r->fees,'r_multiple'=>(float)$r->r_multiple,'exit_reason'=>$r->exit_reason]);
  $curve=[['ms'=>(int)$run->first_open_ms,'equity'=>(float)(json_decode($run->metrics,true)['initial_equity']??1000)]];
  foreach(DB::table('research_trades')->where('backtest_id',$run->id)->orderBy('entry_ms')->get() as $trade){
   $curve[]=['ms'=>(int)$trade->exit_ms,'equity'=>end($curve)['equity']+(float)$trade->net_pnl];
  }
  return response()->json(['run'=>['id'=>$run->id,'symbol'=>$run->symbol,'strategy'=>$run->strategy,
   'parameters'=>json_decode($run->parameters,true),'metrics'=>json_decode($run->metrics,true),
   'bars'=>$run->bars,'first_open_ms'=>(int)$run->first_open_ms,'last_open_ms'=>(int)$run->last_open_ms,
   'created_at'=>$run->created_at],'trades'=>$trades,'equity_curve'=>$curve])
   ->header('Cache-Control','no-store');
 }
}