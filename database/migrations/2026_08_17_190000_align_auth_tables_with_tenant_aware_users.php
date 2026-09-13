<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_tenant_email_unique ON users (tenant_id, email)');
        DB::statement("CREATE UNIQUE INDEX users_owner_email_unique ON users (email) WHERE role = 'OWNER'");

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('email')->constrained('tenants')->cascadeOnDelete();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->after('user_id')->constrained('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        DB::statement('DROP INDEX users_owner_email_unique');
        DB::statement('DROP INDEX users_tenant_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
