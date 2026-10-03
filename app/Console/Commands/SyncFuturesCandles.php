<?php
namespace App\Console\Commands;
use App\MarketData\FuturesImporter;
use Illuminate\Console\Command;
final class SyncFuturesCandles extends Command {
 protected $signature='astra:sync-futures {--pages=10}';
 protected $description='Import completed Binance USD-M futures 1m candles (read-only public API)';
 public function handle(FuturesImporter $importer): int {
  try{$result=$importer->sync(config('astra.symbol'),(int)$this->option('pages'));$this->line(json_encode($result));return self::SUCCESS;}
  catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
 }
}