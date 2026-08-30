<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_key');
            $table->unsignedInteger('credits');
            $table->unsignedInteger('amount');           // minor units (e.g. BDT paisa) or whole BDT — see config
            $table->string('currency', 3)->default('BDT');
            $table->string('gateway');                   // bkash | sslcommerz
            $table->string('status')->default('pending'); // pending | paid | failed | cancelled
            $table->string('gateway_ref')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_orders');
    }
};
