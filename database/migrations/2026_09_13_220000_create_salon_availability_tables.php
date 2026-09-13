<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salon_settings', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Bogota')->after('tenant_id');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15)->after('timezone');
        });

        DB::statement('ALTER TABLE salon_settings ADD CONSTRAINT salon_settings_slot_interval_valid CHECK (slot_interval_minutes > 0 AND slot_interval_minutes <= 60 AND MOD(60, slot_interval_minutes) = 0)');

        Schema::create('salon_weekly_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->boolean('closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'weekday']);
        });

        DB::statement('ALTER TABLE salon_weekly_hours ADD CONSTRAINT salon_weekly_hours_weekday_valid CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE salon_weekly_hours ADD CONSTRAINT salon_weekly_hours_interval_valid CHECK ((closed AND opens_at IS NULL AND closes_at IS NULL) OR (NOT closed AND opens_at IS NOT NULL AND closes_at IS NOT NULL AND opens_at < closes_at))');

        Schema::create('salon_schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at', 'ends_at']);
        });

        DB::statement('ALTER TABLE salon_schedule_blocks ADD CONSTRAINT salon_schedule_blocks_time_valid CHECK (ends_at > starts_at)');

        $now = now();
        $rows = DB::table('salon_settings')->pluck('tenant_id')->flatMap(fn (string $tenantId) => collect(range(0, 6))->map(fn (int $weekday) => [
            'tenant_id' => $tenantId,
            'weekday' => $weekday,
            'closed' => false,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]))->all();

        if ($rows !== []) {
            DB::table('salon_weekly_hours')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_schedule_blocks');
        Schema::dropIfExists('salon_weekly_hours');

        Schema::table('salon_settings', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'slot_interval_minutes']);
        });
    }
};
