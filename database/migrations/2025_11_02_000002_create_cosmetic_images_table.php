<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cosmetic_images')) {
            Schema::create('cosmetic_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cosmetic_id')->constrained('cosmetics')->onDelete('cascade');
            $table->string('type'); // icon, featured, background, icon_background, full_background, juno_icon, beans_icon
            $table->string('url');
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetic_images');
    }
};
