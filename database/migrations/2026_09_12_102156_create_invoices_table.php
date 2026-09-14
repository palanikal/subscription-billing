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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->timestamp('cycle_start');
            $table->timestamp('cycle_end');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('draft');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('total_cents');
            $table->timestamp('generated_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'cycle_start', 'cycle_end']);
            $table->index(['merchant_id', 'cycle_start']);
            $table->index(['customer_id', 'cycle_start']);
            $table->index(['status', 'cycle_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
