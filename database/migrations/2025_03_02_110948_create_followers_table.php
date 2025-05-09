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
        Schema::create('followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('userauths')->onDelete('cascade'); // المستخدم الذي يقوم بالمتابعة
            $table->foreignId('following_id')->constrained('userauths')->onDelete('cascade'); // المستخدم المتابع
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']); // منع التكرار
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('followers');
    }
};
