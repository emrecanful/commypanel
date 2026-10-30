<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 40)->unique();
            $table->string('stack')->nullable();
            $table->string('source_type');
            $table->string('git_url')->nullable();
            $table->string('git_branch')->default('main');
            $table->text('deploy_key_private')->nullable();
            $table->text('deploy_key_public')->nullable();
            $table->unsignedSmallInteger('container_port')->default(8080);
            $table->string('health_path')->default('/');
            $table->unsignedSmallInteger('memory_limit_mb')->nullable();
            $table->decimal('cpu_limit', 4, 2)->nullable();
            $table->unsignedBigInteger('active_deployment_id')->nullable();
            $table->timestamps();
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->index();
            $table->string('commit_sha', 64)->nullable();
            $table->string('image_tag')->nullable();
            $table->string('container_name')->nullable();
            $table->unsignedSmallInteger('host_port')->nullable();
            $table->longText('log')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'number']);
        });

        Schema::create('app_env_vars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['app_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_env_vars');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('apps');
    }
};
