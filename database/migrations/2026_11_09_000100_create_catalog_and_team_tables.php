<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------ Clients
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable : un client créé au salon (back-office) n'a pas forcément de compte.
            $table->foreignUuid('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32)->nullable()->unique();
            $table->string('email', 191)->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->text('preferences')->nullable();
            $table->text('notes')->nullable(); // interne, jamais exposé au client
            $table->string('loyalty_tier', 20)->default('standard');
            $table->date('vip_since')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['last_name', 'first_name']);
        });

        // -------------------------------------------------------- Professionnels
        Schema::create('professionals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 32)->nullable(); // interne
            $table->string('email', 191)->nullable(); // interne
            $table->string('job_title', 100)->default('Barbier');
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_alt')->nullable();
            // Liste courte de libellés affichés tels quels : JSON justifié (pas de requêtes dessus).
            $table->json('specialties')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_placeholder')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // ----------------------------------------------------------- Catalogue
        Schema::create('service_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('tagline', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('service_categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            // null = non communiqué (prix « sur demande », prestation non réservable en ligne).
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('price')->nullable(); // XOF entiers
            $table->boolean('price_from')->default(false);
            $table->string('image')->nullable();
            $table->boolean('available_in_salon')->default(true);
            $table->boolean('available_at_home')->default(false);
            $table->text('conditions')->nullable();
            $table->boolean('is_bookable_online')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_placeholder')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['category_id', 'is_active']);
        });

        // Prestations réalisables par un professionnel ; is_active = activée par le professionnel.
        Schema::create('professional_service', function (Blueprint $table) {
            $table->foreignUuid('professional_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->primary(['professional_id', 'service_id']);
        });

        // ------------------------------------------------------- Horaires salon
        Schema::create('salon_hours', function (Blueprint $table) {
            $table->unsignedTinyInteger('iso_day')->primary(); // 1 = lundi … 7 = dimanche
            $table->boolean('is_closed')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->timestampsTz();
        });

        Schema::create('salon_closures', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('label', 150);
            $table->boolean('is_closed')->default(true);
            $table->timestampsTz();
        });

        // ---------------------------------------- Horaires et pauses professionnels
        Schema::create('working_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('iso_day');
            $table->boolean('is_working')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestampsTz();
            $table->unique(['professional_id', 'iso_day']);
        });

        // Pauses hebdomadaires récurrentes. Les pauses ponctuelles sont des indisponibilités datées.
        Schema::create('schedule_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('iso_day');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason', 150)->nullable();
            $table->timestampsTz();
            $table->index(['professional_id', 'iso_day']);
        });

        // Indisponibilités ponctuelles (quelques heures dans une journée).
        Schema::create('unavailabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('reason', 255)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['professional_id', 'date']);
        });

        // Congés, absences, formations, fermetures (journées entières), avec validation.
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // conge | absence | formation | autre | exception
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 255)->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['professional_id', 'start_date', 'end_date']);
            $table->index('status');
        });

        DB::statement('ALTER TABLE absences ADD CONSTRAINT absences_dates_check CHECK (end_date >= start_date)');
        DB::statement('ALTER TABLE unavailabilities ADD CONSTRAINT unavailabilities_times_check CHECK (end_time > start_time)');
        DB::statement('ALTER TABLE schedule_breaks ADD CONSTRAINT schedule_breaks_times_check CHECK (end_time > start_time)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_day_check CHECK (iso_day BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE salon_hours ADD CONSTRAINT salon_hours_day_check CHECK (iso_day BETWEEN 1 AND 7)');
    }

    public function down(): void
    {
        foreach (['absences', 'unavailabilities', 'schedule_breaks', 'working_hours', 'salon_closures', 'salon_hours',
            'professional_service', 'services', 'service_categories', 'professionals', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
