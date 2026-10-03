<?php
namespace App\MarketData;
use Illuminate\Support\Facades\Http;
use RuntimeException;
final class BinanceFuturesClient {
 public function candles(string $symbol,int $startMs,int $endMs): array {
  if(!preg_match('/^[A-Z0-9]{5,24}$/',$symbol)||$startMs<0||$endMs<=$startMs)throw new \InvalidArgumentException('Invalid candle request');
  $r=Http::acceptJson()->timeout(20)->retry(3,600)->get(rtrim(config('astra.futures_base_url'),'/').'/fapi/v1/klines',
   ['symbol'=>$symbol,'interval'=>'1m','startTime'=>$startMs,'endTime'=>$endMs,'limit'=>1000]);
  if(!$r->successful()||!is_array($r->json()))throw new RuntimeException('Futures API unavailable: HTTP '.$r->status());
  return $r->json();
 }
}