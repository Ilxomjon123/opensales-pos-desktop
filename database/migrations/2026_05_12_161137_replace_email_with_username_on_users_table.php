<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->after('name');
        });

        // Backfill: email -> username. Unique konflikt bo'lsa, raqam suffiks.
        $rows = DB::table('users')->select('id', 'email')->orderBy('id')->get();
        $taken = [];

        foreach ($rows as $row) {
            $base = (string) $row->email;
            $candidate = $base;
            $suffix = 1;

            while (isset($taken[$candidate])) {
                $candidate = $base.'_'.$suffix;
                $suffix++;
            }

            $taken[$candidate] = true;

            DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable(false)->change();
            $table->unique('username');
        });

        // SQLite drop column unique index orqali xato beradi — alohida dropUnique kerak.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email', 'email_verified_at']);
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        DB::statement('UPDATE users SET email = username');

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
            $table->dropColumn('username');
        });
    }
};
