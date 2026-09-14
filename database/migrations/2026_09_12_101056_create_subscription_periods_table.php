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
        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('plan_name_snapshot', 120);
            $table->unsignedBigInteger('base_price_cents_snapshot');
            $table->unsignedBigInteger('included_units_snapshot');
            $table->unsignedBigInteger('overage_rate_cents_snapshot');
            $table->string('billing_cycle_snapshot', 32);
            $table->string('change_reason', 32)->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'starts_at', 'ends_at']);
            $table->index(['customer_id', 'starts_at', 'ends_at']);
            $table->index(['merchant_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_periods');
    }
};
