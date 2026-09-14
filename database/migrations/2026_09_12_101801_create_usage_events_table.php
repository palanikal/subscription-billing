<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('quantity');
            $table->dateTime('occurred_at', 6);
            $table->date('occurred_on');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['customer_id', 'occurred_on', 'id']);
            $table->index(['merchant_id', 'occurred_on', 'customer_id']);
            $table->index(['occurred_on', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
