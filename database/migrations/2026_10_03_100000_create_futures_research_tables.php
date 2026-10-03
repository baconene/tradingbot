<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('futures_candles',function(Blueprint $t){
   $t->id();$t->string('symbol',24);$t->string('interval',8)->default('1m');$t->unsignedBigInteger('open_ms');$t->unsignedBigInteger('close_ms');
   foreach(['open','high','low','close','volume'] as $c)$t->decimal($c,26,10);
   $t->timestamp('imported_at');$t->unique(['symbol','interval','open_ms']);
  });
  Schema::create('research_backtests',function(Blueprint $t){
   $t->id();$t->string('symbol',24);$t->string('strategy',70);$t->json('parameters');$t->json('metrics');
   $t->unsignedInteger('bars');$t->unsignedBigInteger('first_open_ms');$t->unsignedBigInteger('last_open_ms');$t->timestamp('created_at')->useCurrent();
  });
  Schema::create('research_trades',function(Blueprint $t){
   $t->id();$t->foreignId('backtest_id')->constrained('research_backtests')->cascadeOnDelete();$t->string('side',5);
   foreach(['signal_ms','entry_ms','exit_ms'] as $c)$t->unsignedBigInteger($c);
   foreach(['entry','stop','target','exit','quantity','gross_pnl','fees','net_pnl','r_multiple'] as $c)$t->decimal($c,26,10);
   $t->string('exit_reason',30);$t->json('context');$t->index(['backtest_id','entry_ms']);
  });
 }
 public function down(): void {Schema::dropIfExists('research_trades');Schema::dropIfExists('research_backtests');Schema::dropIfExists('futures_candles');}
};