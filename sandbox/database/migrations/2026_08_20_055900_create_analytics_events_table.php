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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50)->index();
            $table->string('path', 255);
            $table->char('visitor_hash', 64)->index();
            $table->foreignId('video_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('progress_seconds')->nullable();
            $table->string('device', 40)->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
