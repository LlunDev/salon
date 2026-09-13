<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('appointment_capacity')->default(1);
            $table->unsignedSmallInteger('cancellation_notice_hours')->default(24);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE salon_settings ADD CONSTRAINT salon_settings_capacity_positive CHECK (appointment_capacity >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_settings');
    }
};
