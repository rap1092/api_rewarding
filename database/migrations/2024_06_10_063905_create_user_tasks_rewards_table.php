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
        Schema::create('user_tasks_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('userTgId');
            $table->string('taskId');
            $table->float('amount');
            $table->enum('status',['start','pending','claimed']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tasks_rewards');
    }
};
