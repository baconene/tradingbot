<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('futures_strategy_configs',function(Blueprint $t){
   $t->id();$t->uuid('version')->unique();$t->string('name',100);$t->string('symbol',30)->default('BTCUSDT');
   $t->string('timeframe',10)->default('1h');$t->string('market','20')->default('usdm_futures');
   $t->string('status',20)->default('draft');$t->json('parameters');$t->char('parameters_hash',64);
   $t->timestamps();$t->index(['status','created_at']);
  });
  Schema::create('futures_market_observations',function(Blueprint $t){
   $t->id();$t->string('exchange',30);$t->string('symbol',30);$t->string('interval',10);
   $t->timestampTz('open_time');$t->timestampTz('close_time');$t->decimal('open',28,12);
   $t->decimal('high',28,12);$t->decimal('low',28,12);$t->decimal('close',28,12);
   $t->decimal('volume',28,12);$t->decimal('mark_price',28,12)->nullable();
   $t->decimal('funding_rate',18,12)->nullable();$t->decimal('open_interest',28,12)->nullable();
   $t->timestampTz('received_at');$t->string('source',60);$t->char('payload_hash',64);$t->timestamps();
   $t->unique(['exchange','symbol','interval','open_time'],'futures_observation_unique');
  });
  Schema::create('futures_execution_events',function(Blueprint $t){
   $t->id();$t->string('environment',20);$t->string('exchange',30);
   $t->string('event_key',150);$t->string('event_type',50);$t->string('client_order_id',100)->nullable();
   $t->json('payload');$t->timestampTz('occurred_at');$t->timestampTz('received_at');$t->timestamps();
   $t->unique(['environment','exchange','event_key'],'futures_event_unique');
  });
  Schema::create('futures_risk_snapshots',function(Blueprint $t){
   $t->id();$t->string('environment',20);$t->string('exchange',30);
   $t->decimal('equity',28,12);$t->decimal('available_margin',28,12);
   $t->decimal('maintenance_margin',28,12);$t->decimal('gross_notional',28,12);
   $t->decimal('daily_pnl',28,12);$t->boolean('reconciled')->default(false);
   $t->boolean('kill_latched')->default(true);$t->timestampTz('observed_at');$t->timestamps();
   $t->index(['environment','observed_at']);
  });
 }
 public function down(): void {foreach(['futures_risk_snapshots','futures_execution_events','futures_market_observations','futures_strategy_configs'] as $table)Schema::dropIfExists($table);}
};
