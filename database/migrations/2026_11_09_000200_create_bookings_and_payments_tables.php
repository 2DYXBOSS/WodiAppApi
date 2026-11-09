<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nécessaire à la contrainte d'exclusion (égalité sur uuid dans un index GiST).
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('professional_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('service_id')->constrained()->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->string('location', 20)->default('salon');
            // Instantanés : l'historique reste juste si la prestation change ensuite.
            $table->string('service_name_snapshot', 150);
            $table->unsignedInteger('price_snapshot')->nullable();
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('status', 20);
            $table->string('payment_status', 20)->default('pending');
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('reminder_sent_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['professional_id', 'starts_at', 'status']);
            $table->index(['customer_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_range_check CHECK (ends_at > starts_at)');
        // Garantie ultime contre les doubles réservations : deux réservations actives
        // d'un même professionnel ne peuvent jamais se chevaucher, même en cas de concurrence.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (professional_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
            WHERE (status IN ('pending', 'confirmed', 'in_progress', 'completed'))
        SQL);

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('action', 150);
            $table->string('detail', 500)->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name', 200);
            $table->timestampTz('created_at')->useCurrent();
            $table->index('booking_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('XOF');
            $table->string('method', 20); // cash | orange_money | mtn_momo | wave | card | other
            $table->string('status', 20); // pending | paid | partial | failed | refunded
            // Référence du prestataire (idempotence des futurs webhooks).
            $table->string('transaction_reference', 120)->nullable()->unique();
            $table->string('note', 500)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
            $table->index(['customer_id', 'paid_at']);
            $table->index(['status', 'paid_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
    }
};
