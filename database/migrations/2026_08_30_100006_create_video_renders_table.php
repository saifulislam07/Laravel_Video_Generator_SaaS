<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('shotstack_render_id')->nullable();
            $table->string('status')->default('queued'); // queued | rendering | done | failed
            $table->string('output_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
            $table->index('shotstack_render_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_renders');
    }
};
