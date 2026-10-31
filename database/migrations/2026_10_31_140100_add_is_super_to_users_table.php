<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The account created through the setup link. It can do everything, cannot be
            // changed or deleted from the panel, and is recovered with `admin:reset`.
            $table->boolean('is_super')->default(false)->after('email');
        });

        // Installations that predate roles: their first account is the super user.
        $first = DB::table('users')->orderBy('id')->value('id');

        if ($first !== null) {
            DB::table('users')->where('id', $first)->update(['is_super' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super');
        });
    }
};
