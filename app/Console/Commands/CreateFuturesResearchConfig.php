<?php
namespace App\Console\Commands;
use App\Futures\StrategyConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class CreateFuturesResearchConfig extends Command
{
 protected $signature='astra:futures-config {name} {--leverage=2} {--rr=2} {--lookback=20}';
 protected $description='Create an immutable draft futures research configuration (never enables trading)';
 public function handle(StrategyConfiguration $config): int
 {
  try{$params=$config->normalize(array_replace(StrategyConfiguration::DEFAULTS,[
   'leverage'=>(int)$this->option('leverage'),'reward_risk'=>(float)$this->option('rr'),
   'structure_lookback'=>(int)$this->option('lookback')]));}
  catch(\InvalidArgumentException $e){$this->error($e->getMessage());return self::FAILURE;}
  $version=(string)Str::uuid();
  DB::table('futures_strategy_configs')->insert(['version'=>$version,'name'=>$this->argument('name'),
   'status'=>'draft','parameters'=>json_encode($params,JSON_THROW_ON_ERROR),
   'parameters_hash'=>$config->hash($params),'created_at'=>now(),'updated_at'=>now()]);
  $this->info("Draft created: $version. No order or model promotion.");
  return self::SUCCESS;
 }
}
