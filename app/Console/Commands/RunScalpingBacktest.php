<?php
namespace App\Console\Commands;
use App\Backtesting\ScalpingBacktester;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class RunScalpingBacktest extends Command {
 protected $signature='astra:backtest-scalping {--rr=2} {--risk=0.005} {--bars=15000}';
 protected $description='Run offline 5m HH/LL + 15m trend backtest against completed 1m futures candles';
 public function handle(ScalpingBacktester $engine): int {
  $bars=(int)$this->option('bars');if($bars<900||$bars>50000){$this->error('Bars must be 900–50000');return self::FAILURE;}
  $symbol=config('astra.symbol');
  $minutes=DB::table('futures_candles')->where('symbol',$symbol)->where('interval','1m')
   ->orderByDesc('open_ms')->limit($bars)->get()->reverse()->values()
   ->map(fn($r)=>['open_ms'=>(int)$r->open_ms,'close_ms'=>(int)$r->close_ms,'open'=>(float)$r->open,
    'high'=>(float)$r->high,'low'=>(float)$r->low,'close'=>(float)$r->close,'volume'=>(float)$r->volume])->all();
  try{$result=$engine->run($minutes,['rr'=>(float)$this->option('rr'),'risk_pct'=>(float)$this->option('risk')]);}
  catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
  $id=DB::transaction(function()use($result,$minutes,$symbol){
   $id=DB::table('research_backtests')->insertGetId(['symbol'=>$symbol,'strategy'=>$result['strategy'],
    'parameters'=>json_encode($result['parameters']),'metrics'=>json_encode($result['metrics']),
    'bars'=>count($minutes),'first_open_ms'=>$minutes[0]['open_ms'],'last_open_ms'=>end($minutes)['open_ms'],'created_at'=>now()]);
   foreach(array_chunk($result['trades'],200) as $batch){
    DB::table('research_trades')->insert(array_map(fn($t)=>array_merge($t,
     ['backtest_id'=>$id,'context'=>json_encode($t['context'])]),$batch));
   }return $id;
  });
  $this->info('Backtest #'.$id.' '.json_encode($result['metrics']));return self::SUCCESS;
 }
}