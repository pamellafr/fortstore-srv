<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_cosmetics')) {
            Schema::create('user_cosmetics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('cosmetic_id')->constrained()->onDelete('cascade');
                $table->integer('purchase_price')->nullable();
                $table->timestamp('purchased_at')->useCurrent();
                $table->timestamps();
                
                $table->unique(['user_id', 'cosmetic_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cosmetics');
    }
};

