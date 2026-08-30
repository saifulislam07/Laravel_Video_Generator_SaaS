<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_pose_id')->constrained()->cascadeOnDelete();
            $table->float('position_x')->default(0);   // horizontal offset (px or %) on the 1080x1920 canvas
            $table->float('position_y')->default(0);   // vertical offset (px or %)
            $table->float('scale')->default(1);        // 1 = original PNG size
            $table->timestamps();

            $table->index('scene_id');
            $table->index('character_pose_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_characters');
    }
};
