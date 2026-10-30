<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            // Null once the setup wizard is finished (and for apps that predate it).
            $table->string('setup_step')->nullable()->after('slug');
            // The app type the user picked; blank means "detect it from the code".
            $table->string('stack_hint')->nullable()->after('stack');
            // When the source was last confirmed readable (public repo, working deploy key, or an upload).
            $table->timestamp('source_verified_at')->nullable()->after('deploy_key_public');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['setup_step', 'stack_hint', 'source_verified_at']);
        });
    }
};
