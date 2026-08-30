<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_renders', function (Blueprint $table) {
            $table->string('source_url')->nullable()->after('output_url'); // original Shotstack CDN url
            $table->timestamp('archived_at')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('video_renders', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'archived_at']);
        });
    }
};
