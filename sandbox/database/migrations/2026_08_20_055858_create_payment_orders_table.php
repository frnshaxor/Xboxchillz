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
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 50)->unique();
            $table->string('buyer_name', 120);
            $table->string('buyer_contact', 200);
            $table->unsignedInteger('amount');
            $table->string('status', 30)->default('pending')->index();
            $table->string('snap_token', 100)->nullable();
            $table->char('access_secret_hash', 64);
            $table->foreignId('access_token_id')->nullable()->constrained('access_tokens')->nullOnDelete();
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('payment_type', 60)->nullable();
            $table->json('notification_payload')->nullable();
            $table->ipAddress('client_ip')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
