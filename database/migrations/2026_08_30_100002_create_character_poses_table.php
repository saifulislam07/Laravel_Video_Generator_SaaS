<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_poses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('pose_name');
            $table->string('image_path');
            $table->timestamps();

            $table->index('character_id');
            $table->unique(['character_id', 'pose_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_poses');
    }
};
