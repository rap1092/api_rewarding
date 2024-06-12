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
        Schema::create('farmings', function (Blueprint $table) {
            $table->id();
            $table->string('userTgId');
            $table->string('transactionId');
            $table->dateTime('startFarmingDate');
            $table->dateTime('targetFarmingDate');
            $table->float('reward');
            $table->enum('status',['farming','claimed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmings');
    }
};
