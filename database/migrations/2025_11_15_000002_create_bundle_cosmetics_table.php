<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bundle_cosmetics')) {
            Schema::create('bundle_cosmetics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_cosmetic_id')->constrained('cosmetics')->onDelete('cascade'); 
                $table->foreignId('cosmetic_id')->constrained('cosmetics')->onDelete('cascade'); 
                $table->timestamps();
                
                $table->unique(['bundle_cosmetic_id', 'cosmetic_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_cosmetics');
    }
};

