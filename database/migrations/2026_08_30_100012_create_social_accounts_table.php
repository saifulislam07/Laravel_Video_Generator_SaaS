<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');           // facebook_page | instagram
            $table->string('provider_account_id'); // page id or ig user id
            $table->string('name');
            $table->text('access_token');          // page access token (long-lived)
            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'provider_account_id'], 'social_accounts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
