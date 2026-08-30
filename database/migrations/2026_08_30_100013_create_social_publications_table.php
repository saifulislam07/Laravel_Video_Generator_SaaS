<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_render_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | published | failed
            $table->string('external_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('video_render_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publications');
    }
};
