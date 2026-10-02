<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('strategies', function (Blueprint $table) {
            $table->id(); $table->string('code', 32); $table->string('version', 32); $table->string('status', 20)->default('draft');
            $table->json('parameters'); $table->timestamps(); $table->unique(['code','version']);
        });
        Schema::create('risk_events', function (Blueprint $table) {
            $table->id(); $table->string('event_type', 60); $table->string('severity', 20);
            $table->json('details')->nullable(); $table->timestampTz('occurred_at'); $table->timestamps();
        });
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id(); $table->string('actor', 120); $table->string('action', 120);
            $table->json('details')->nullable(); $table->timestampTz('occurred_at'); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('system_audit_logs'); Schema::dropIfExists('risk_events'); Schema::dropIfExists('strategies');
    }
};
