<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmetics', function (Blueprint $table) {
            $table->id();
            $table->string('cosmetic_id')->unique(); // id do JSON
            $table->string('type_id')->nullable();
            $table->string('type_name')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('rarity_id')->nullable();
            $table->string('rarity_name')->nullable();
            $table->string('series')->nullable();
            $table->integer('price')->nullable();
            $table->date('added_date')->nullable();
            $table->string('added_version')->nullable();
            $table->boolean('copyrighted_audio')->default(false);
            $table->boolean('upcoming')->default(false);
            $table->boolean('reactive')->default(false);
            $table->date('release_date')->nullable();
            $table->date('last_appearance')->nullable();
            $table->float('interest')->nullable();
            $table->string('path')->nullable();
            $table->json('gameplay_tags')->nullable();
            $table->json('api_tags')->nullable();
            $table->json('battlepass')->nullable();
            $table->json('set')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetics');
    }
};
