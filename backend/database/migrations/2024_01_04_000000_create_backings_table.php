<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('tier_id')->nullable()->constrained('campaign_tiers');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backings');
    }
};
