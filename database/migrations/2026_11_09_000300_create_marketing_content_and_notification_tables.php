<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('discount_type', 10); // percent | amount
            $table->unsignedInteger('value');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->index(['is_active', 'start_date', 'end_date']);
        });
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_dates_check CHECK (end_date >= start_date)');

        // Historique de fidélité (visites, changements de statut VIP…). Le statut courant est sur customers.
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('type', 20); // visit | tier_change | manual
            $table->integer('points')->default(0);
            $table->string('label', 255);
            $table->date('occurred_on');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['customer_id', 'occurred_on']);
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('alt', 255)->nullable();
            $table->string('category', 20); // coupes | barbe | salon | details
            $table->string('orientation', 10)->default('portrait');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_illustration')->default(false);
            $table->timestampsTz();
            $table->index(['is_published', 'sort_order']);
        });

        // Paramètres et contenus : une ligne par section, structure validée par l'API.
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->string('phone', 32)->nullable();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('status', 20)->default('new'); // new | read | handled | spam
            $table->timestampTz('read_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 20)->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'device_tokens', 'notifications', 'contact_messages', 'site_settings',
            'gallery_images', 'loyalty_transactions', 'promotions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
