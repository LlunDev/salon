<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('users')->restrictOnDelete();
            $table->uuid('booking_key');
            $table->string('status', 16)->default('confirmed');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('duration_minutes');
            $table->decimal('total', 12, 2);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('notify_client')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_id', 'booking_key']);
            $table->index(['tenant_id', 'status', 'starts_at', 'ends_at']);
        });

        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_valid CHECK (status IN ('confirmed', 'cancelled', 'completed'))");
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_time_valid CHECK (ends_at > starts_at)');

        Schema::create('appointment_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('salon_service_id')->nullable()->constrained('salon_services')->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['appointment_id', 'salon_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
        Schema::dropIfExists('appointments');
    }
};
