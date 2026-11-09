<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            // E-mail toujours stocké en minuscules (unicité insensible à la casse).
            $table->string('email', 191)->unique();
            $table->string('phone', 32)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->string('avatar_path')->nullable();
            // Nullable : un compte invité (administrateur, barbier) définit son mot de passe via le lien reçu.
            $table->string('password')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            // Préférences de notification (mobile).
            $table->boolean('notify_booking_updates')->default(true);
            $table->boolean('notify_reminders')->default(true);
            $table->boolean('notify_promotions')->default(false);
            $table->rememberToken();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Jetons de réinitialisation : seul le hash SHA-256 est stocké, lien à usage unique.
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_codes');
        Schema::dropIfExists('users');
    }
};
