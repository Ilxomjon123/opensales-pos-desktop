<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique(['telegram_id']);
            $table->dropColumn('telegram_id');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('deliveryman_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['deliveryman_id']);
        });

        Schema::create('shop_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('telegram_id')->unique();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'is_active']);
        });

        Schema::create('shop_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->bigInteger('used_by_telegram_id')->nullable();
            $table->timestamps();

            $table->index(['shop_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('member_id')
                ->nullable()
                ->after('shop_id')
                ->constrained('shop_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });

        Schema::dropIfExists('shop_invites');
        Schema::dropIfExists('shop_members');

        Schema::table('shops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deliveryman_id');
            $table->bigInteger('telegram_id')->unique()->nullable();
        });
    }
};
