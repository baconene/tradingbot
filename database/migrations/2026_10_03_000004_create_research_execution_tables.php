<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('backtest_runs',function(Blueprint $t){$t->id();$t->string('strategy_version');$t->timestampTz('data_start');$t->timestampTz('data_end');$t->char('data_hash',64);$t->json('results');$t->timestamps();});
        Schema::create('model_versions',function(Blueprint $t){$t->id();$t->string('version')->unique();$t->string('status')->default('observation');$t->json('metrics')->nullable();$t->timestampTz('approved_at')->nullable();$t->timestamps();});
        Schema::create('predictions',function(Blueprint $t){$t->id();$t->foreignId('market_snapshot_id')->constrained('market_snapshots');$t->foreignId('model_version_id')->constrained('model_versions');$t->decimal('probability',10,8);$t->json('metadata')->nullable();$t->timestamps();$t->unique(['market_snapshot_id','model_version_id']);});
        Schema::create('prediction_outcomes',function(Blueprint $t){$t->id();$t->foreignId('prediction_id')->unique()->constrained('predictions');$t->boolean('target_before_stop')->nullable();$t->timestampTz('resolved_at')->nullable();$t->timestamps();});
        Schema::create('risk_state',function(Blueprint $t){$t->id();$t->boolean('kill_latched')->default(true);$t->boolean('reconciled')->default(false);$t->decimal('daily_reference',20,8)->default(1000);$t->decimal('high_water',20,8)->default(1000);$t->timestampTz('last_reconciled_at')->nullable();$t->timestamps();});
        Schema::create('trade_intents',function(Blueprint $t){$t->id();$t->uuid('decision_id')->unique();$t->foreignId('market_snapshot_id')->nullable()->constrained('market_snapshots');$t->string('strategy_version');$t->string('status')->default('intent');$t->json('payload');$t->json('risk_decision')->nullable();$t->timestamps();});
        Schema::create('orders',function(Blueprint $t){$t->id();$t->foreignId('trade_intent_id')->unique()->constrained('trade_intents');$t->string('client_order_id')->unique();$t->string('exchange_order_id')->nullable()->unique();$t->string('state')->default('intent');$t->json('request');$t->timestamps();});
        Schema::create('order_events',function(Blueprint $t){$t->id();$t->foreignId('order_id')->constrained('orders');$t->string('external_event_id')->nullable()->unique();$t->string('from_state');$t->string('to_state');$t->json('payload')->nullable();$t->timestampTz('occurred_at');$t->timestamps();});
        Schema::create('fills',function(Blueprint $t){$t->id();$t->foreignId('order_id')->constrained('orders');$t->string('exchange_fill_id')->unique();$t->decimal('quantity',24,12);$t->decimal('price',24,12);$t->decimal('fee',24,12);$t->string('fee_asset');$t->timestampTz('filled_at');$t->timestamps();});
        Schema::create('positions',function(Blueprint $t){$t->id();$t->string('symbol');$t->string('portfolio');$t->decimal('quantity',24,12);$t->decimal('average_price',24,12);$t->string('status');$t->timestamps();});
        Schema::create('portfolio_snapshots',function(Blueprint $t){$t->id();$t->string('portfolio');$t->decimal('equity',24,12);$t->decimal('cash',24,12);$t->decimal('unrealized_pnl',24,12);$t->timestampTz('observed_at');$t->timestamps();});
    }
    public function down(): void {foreach(['portfolio_snapshots','positions','fills','order_events','orders','trade_intents','risk_state','prediction_outcomes','predictions','model_versions','backtest_runs'] as $table) Schema::dropIfExists($table);}
};
