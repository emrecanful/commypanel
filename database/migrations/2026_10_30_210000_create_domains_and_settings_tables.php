<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('status')->default('pending')->index();
            $table->foreignId('app_id')->nullable()->constrained()->nullOnDelete();
            $table->json('last_check')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('settings');
    }
};
