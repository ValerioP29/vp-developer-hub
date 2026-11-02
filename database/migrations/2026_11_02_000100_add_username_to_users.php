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
            $table->string('username', 64)->nullable();
        });

        // No Eloquent events, password hashing or changes to existing account data.
        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['username' => 'user-'.$user->id]);
            }
        });

        // PostgreSQL and SQLite: uniqueness also holds for writes outside Eloquent.
        DB::statement('CREATE UNIQUE INDEX users_username_unique ON users (LOWER(username))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_username_unique');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('username'));
    }
};
