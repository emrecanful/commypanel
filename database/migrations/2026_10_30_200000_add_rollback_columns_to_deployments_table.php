<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->boolean('image_available')->default(true)->after('image_tag');
            $table->unsignedBigInteger('rolled_back_from_id')->nullable()->after('image_available');
        });
    }

    public function down(): void
    {
        Schema::table('deployments', function (Blueprint $table) {
            $table->dropColumn(['image_available', 'rolled_back_from_id']);
        });
    }
};
