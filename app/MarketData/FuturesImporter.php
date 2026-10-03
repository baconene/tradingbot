<?php
namespace App\MarketData;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class FuturesImporter {
 public function __construct(private BinanceFuturesClient $client){}
 public function sync(string $symbol,int $pages=10,?int $nowMs=null): array {
  if($pages<1||$pages>100||!preg_match('/^[A-Z0-9]{5,24}$/',$symbol))throw new \InvalidArgumentException('Invalid import options');
  $nowMs??=(int)floor(microtime(true)*1000);
  $last=DB::table('futures_candles')->where('symbol',$symbol)->where('interval','1m')->max('open_ms');
  $cursor=$last===null?$nowMs-$pages*1000*60000:(int)$last+60000;
  $end=intdiv($nowMs,60000)*60000-1;$count=0;$gaps=0;$previous=$last===null?null:(int)$last;
  for($page=0;$page<$pages&&$cursor<$end;$page++){
   $rows=$this->client->candles($symbol,$cursor,$end);if(!$rows)break;$batch=[];$next=$cursor;
   foreach($rows as $row){
    if(!is_array($row)||count($row)<7)throw new RuntimeException('Malformed Binance candle');
    $ms=(int)$row[0];$closeMs=(int)$row[6];
    if($ms<$cursor||$closeMs>=$nowMs)continue;
    foreach([1,2,3,4,5] as $j)if(!is_numeric($row[$j])||!is_finite((float)$row[$j]))throw new RuntimeException('Non-numeric OHLCV');
    [$o,$h,$l,$c,$v]=array_map('floatval',array_slice($row,1,5));
    if($o<=0||$l<=0||$h<max($o,$c)||$l>min($o,$c)||$h<$l||$v<0||$closeMs<$ms)throw new RuntimeException('Invalid OHLCV');
    if($previous!==null&&$ms<=$previous)continue;
    if($previous!==null&&$ms>$previous+60000)$gaps+=intdiv($ms-$previous,60000)-1;
    $batch[]=['symbol'=>$symbol,'interval'=>'1m','open_ms'=>$ms,'close_ms'=>$closeMs,'open'=>$row[1],'high'=>$row[2],
     'low'=>$row[3],'close'=>$row[4],'volume'=>$row[5],'imported_at'=>now()];
    $previous=$ms;$next=$ms+60000;
   }
   if($batch){DB::table('futures_candles')->upsert($batch,['symbol','interval','open_ms'],['close_ms','open','high','low','close','volume','imported_at']);$count+=count($batch);}
   if($next<=$cursor)break;$cursor=$next;if(count($rows)<1000)break;
  }
  return ['imported'=>$count,'gaps_observed'=>$gaps,'last_open_ms'=>$previous,'cutoff_ms'=>$end];
 }
}