<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('market_candles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 24); $table->string('interval', 8);
            $table->dateTimeTz('open_time', 3); $table->dateTimeTz('close_time', 3);
            foreach (['open','high','low','close','volume','quote_volume'] as $field) $table->decimal($field, 28, 10);
            $table->unsignedBigInteger('trade_count');
            $table->dateTimeTz('available_at', 3);
            $table->string('source', 32)->default('binance_spot');
            $table->timestampsTz();
            $table->unique(['symbol','interval','open_time']);
            $table->index(['symbol','interval','close_time']);
        });
        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id(); $table->foreignId('candle_id')->unique()->constrained('market_candles')->restrictOnDelete();
            $table->string('symbol',24); $table->string('interval',8);
            $table->dateTimeTz('decision_time', 3);
            $table->string('feature_version',32); $table->json('features');
            $table->string('input_hash',64); $table->json('data_quality');
            $table->timestampsTz();
            $table->index(['symbol','interval','decision_time']);
        });
    }
    public function down(): void { Schema::dropIfExists('market_snapshots'); Schema::dropIfExists('market_candles'); }
};
