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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('invoice_code')->unique();
            $table->string('external_id')->nullable()->index(); // ID from payment gateway
            $table->string('payment_method')->nullable(); // manual, gateway
            $table->string('payment_channel')->nullable(); // e.g. bca_va, alfamart
            $table->string('payment_url')->nullable(); // for snap/checkout link
            $table->string('payment_reference')->nullable(); // Reference from manual bank transfer
            $table->string('payment_proof')->nullable(); // Path to proof image
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, success, failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
