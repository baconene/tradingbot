<?php
namespace App\MarketData;
final class ScalpingFeatures {
 public function aggregate(array $minutes,int $tf): array {
  if(!in_array($tf,[1,5,15],true))throw new \InvalidArgumentException('Unsupported timeframe');
  $groups=[];foreach($minutes as $b)$groups[intdiv((int)$b['open_ms'],$tf*60000)*$tf*60000][]=$b;
  $out=[];foreach($groups as $key=>$bars){
   if(count($bars)!==$tf)continue;
   usort($bars,fn($a,$b)=>$a['open_ms']<=>$b['open_ms']);
   $ok=true;foreach($bars as $i=>$b)if((int)$b['open_ms']!==$key+$i*60000){$ok=false;break;}
   if(!$ok)continue;$last=$bars[$tf-1];
   $out[]=['open_ms'=>$key,'close_ms'=>(int)$last['close_ms'],'open'=>(float)$bars[0]['open'],
    'high'=>max(array_map(fn($b)=>(float)$b['high'],$bars)),'low'=>min(array_map(fn($b)=>(float)$b['low'],$bars)),
    'close'=>(float)$last['close'],'volume'=>array_sum(array_map(fn($b)=>(float)$b['volume'],$bars))];
  }usort($out,fn($a,$b)=>$a['open_ms']<=>$b['open_ms']);return $out;
 }
 public function indicators(array $bars): array {
  $out=[];$ema20=$ema50=$ema200=null;$gains=[];$losses=[];$trs=[];$prev=null;
  foreach($bars as $i=>$b){
   $c=(float)$b['close'];$ema20=$ema20===null?$c:$ema20+2/21*($c-$ema20);
   $ema50=$ema50===null?$c:$ema50+2/51*($c-$ema50);
   $ema200=$ema200===null?$c:$ema200+2/201*($c-$ema200);
   if($prev!==null){
    $d=$c-(float)$prev['close'];$gains[]=max(0,$d);$losses[]=max(0,-$d);
    $trs[]=max($b['high']-$b['low'],abs($b['high']-$prev['close']),abs($b['low']-$prev['close']));
   }
   if(count($gains)>14)array_shift($gains);if(count($losses)>14)array_shift($losses);if(count($trs)>14)array_shift($trs);
   $rsi=null;$atr=null;
   if(count($gains)===14){$g=array_sum($gains)/14;$l=array_sum($losses)/14;$rsi=$l==0?($g==0?50.:100.):100-100/(1+$g/$l);}
   if(count($trs)===14)$atr=array_sum($trs)/14;
   $lookback=array_slice($bars,max(0,$i-20),min($i,20));
   $out[]=$b+['ema20'=>$i>=19?$ema20:null,'ema50'=>$i>=49?$ema50:null,'ema200'=>$i>=199?$ema200:null,
    'rsi14'=>$rsi,'atr14'=>$atr,'previous_high'=>count($lookback)===20?max(array_column($lookback,'high')):null,
    'previous_low'=>count($lookback)===20?min(array_column($lookback,'low')):null];$prev=$b;
  }return $out;
 }
 public function contiguous(array $bars): bool {
  for($i=1;$i<count($bars);$i++)if((int)$bars[$i]['open_ms']!==(int)$bars[$i-1]['open_ms']+60000)return false;
  return true;
 }
}